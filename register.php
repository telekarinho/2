<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('../common/init.loader.php');

$page_header = $LANG['g_registration'];
include('../common/pub.header.php');

include('../common/sys.captch.php');
$ishcapcok = do_hcaptcha('ishcapcregin', $_POST['h-captcha-response']);
$isrecapv3 = do_recaptcha('isrcapcregin', $_POST['g-recaptcha-response']);

// NOVO: Cadastro via Stripe Connect
if (isset($_GET['type']) && $_GET['type'] === 'stripe') {
    $idref = isset($_GET['ref']) ? intval($_GET['ref']) : 0;
    if (
        (!isset($_GET['action']) || $_GET['action'] !== 'onboard') &&
        !isset($_GET['account'])
    ) {
        header('Location: ?type=stripe&action=onboard&ref=' . urlencode($idref));
        exit;
    }
    $show_msg = $_SESSION['show_msg'] ?? '';
    $_SESSION['show_msg'] = '';
    $form_loading = false;
    $basedir = dirname(__DIR__, 1);
    define('OK_LOADME', true);
    include_once('../modules/payment/stripe_config.php');
    if (file_exists($basedir . '/vendor/autoload.php')) {
        require_once($basedir . '/vendor/autoload.php');
    } elseif (file_exists($basedir . '/common/stripe-php/init.php')) {
        require_once($basedir . '/common/stripe-php/init.php');
    }
    \Stripe\Stripe::setApiKey(get_stripe_secret_key());

    // Handler de retorno do onboarding Stripe
    if (isset($_GET['account'])) {
        $stripe_account_id = $_GET['account'];
        $idref = isset($_GET['ref']) ? intval($_GET['ref']) : 0;
        try {
            $account = \Stripe\Account::retrieve($stripe_account_id);
            error_log(print_r($account, true)); // Log para debug
            write_debug_log('Stripe Account: ' . print_r($account, true));
            if ($account->details_submitted === true || $account->charges_enabled === true) {
                $phone = $account->individual->phone ?? $account->company->phone ?? $account->phone ?? '';
                $email = $account->individual->email ?? $account->email ?? '';
                $firstname = $account->individual->first_name ?? '';
                $lastname = $account->individual->last_name ?? '';
                $country = $account->country ?? '';
                $username = '';

                // Gera username a partir do e-mail (sem caracteres especiais)
                if ($phone && preg_match('/^\+?\d{10,15}$/', $phone)) {
                    $username = preg_replace('/\D/', '', $phone);
                } elseif ($email) {
                    $username = preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]);
                    // Garante que o username seja único
                    $base_username = $username;
                    $i = 1;
                    while (!empty($db->getRecFrmQry("SELECT id FROM netw_mbrs WHERE username = '{$username}' LIMIT 1"))) {
                        $username = $base_username . $i;
                        $i++;
                    }
                } else {
                    $username = $stripe_account_id;
                }

                // Permite cadastro mesmo sem telefone, mas exige e-mail e nome
                if (!$email || !$firstname || !$lastname) {
                    $show_msg = showalert('danger', 'Erro', 'Dados incompletos retornados do Stripe.<br>Phone: ' . htmlspecialchars($phone) . '<br>Email: ' . htmlspecialchars($email) . '<br>Nome: ' . htmlspecialchars($firstname) . ' ' . htmlspecialchars($lastname));
                    write_debug_log('Dados incompletos: Phone=' . $phone . ' Email=' . $email . ' Nome=' . $firstname . ' ' . $lastname);
                } else {
                    $checkUser = $db->getRecFrmQry("SELECT id FROM netw_mbrs WHERE username = '{$username}' LIMIT 1");
                    if (empty($checkUser)) {
                        $senha = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                        $hashedPassword = password_hash($senha, PASSWORD_BCRYPT);
                        $data = [
                            'in_date' => date('Y-m-d H:i:s'),
                            'firstname' => $firstname,
                            'lastname' => $lastname,
                            'email' => $email,
                            'country' => $country,
                            'username' => $username,
                            'password' => $hashedPassword,
                            'idref' => $idref,
                            'stripe_account_id' => $stripe_account_id,
                            'stripe_account_status' => 'verified',
                            'stripe_country' => $country
                        ];
                        $insert = $db->insert('netw_mbrs', $data);
                        $userid = $db->lastInsertId();
                        if ($insert && $userid) {
                            write_debug_log('Usuário criado com sucesso: ' . print_r($data, true));
                            // Envia WhatsApp se possível
                            if ($phone && function_exists('send_whatsapp_message')) {
                                $msg = "Olá, seu cadastro via Stripe foi concluído!\nUsuário: $username\nSenha: $senha\nAcesse: https://7c1.pro/member/login.php";
                                send_whatsapp_message($phone, $msg);
                            }
                            $_SESSION['unox'] = $userid;
                            header('Location: member/index.php?hal=dashboard');
                            exit;
                        } else {
                            write_debug_log('Erro ao inserir usuário: ' . print_r($data, true));
                            $show_msg = showalert('danger', 'Erro', 'Falha ao criar usuário no banco. Verifique permissões e campos obrigatórios.');
                        }
                    } else {
                        $show_msg = showalert('danger', 'Erro', 'Usuário já cadastrado.');
                    }
                }
            } else {
                $show_msg = showalert('danger', 'Onboarding não concluído', 'Finalize seu cadastro na Stripe para continuar.');
            }
        } catch (Exception $e) {
            error_log("Erro ao criar usuário Stripe: " . $e->getMessage());
            error_log(print_r($account, true));
            $show_msg = showalert('danger', 'Erro Stripe', $e->getMessage());
        }
    }
    
    // Handler para iniciar o onboarding Stripe
    if (isset($_GET['action']) && $_GET['action'] === 'onboard' && !isset($_GET['account'])) {
        // Detectar país via IP, ou usar padrão do config
        $ipinfo = @json_decode(file_get_contents("https://ipinfo.io/json"), true);
        $stripe_config = get_stripe_config();
        $country = $ipinfo['country'] ?? $stripe_config['onboarding']['country'] ?? 'BR';

        // Cria conta Stripe Express
        $account = \Stripe\Account::create([
            'type' => 'express',
            'country' => $country,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'business_type' => 'individual',
            'metadata' => [
                'ref' => $idref
            ]
        ]);

        // Gera link de onboarding
        $onboarding = \Stripe\AccountLink::create([
            'account' => $account->id,
            'refresh_url' => 'https://7c1.pro/member/register.php?type=stripe&action=onboard&ref=' . urlencode($idref),
            'return_url' => 'https://7c1.pro/member/register.php?type=stripe&account=' . $account->id . '&ref=' . urlencode($idref),
            'type' => 'account_onboarding',
        ]);

        // Redireciona para o Stripe
        header('Location: ' . $onboarding->url);
        exit;
    }

    // 3. Exibe botão one-click
    if (!isset($_GET['action']) && !isset($_GET['account'])) {
        ?>
        <section class="section">
            <div class="container mt-4">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-8 col-lg-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white"><h5 class="mb-0">Cadastro via Stripe Connect</h5></div>
                            <div class="card-body text-center">
                                <?php echo $show_msg; ?>
                                <a href="?type=stripe&action=onboard" class="btn btn-primary btn-lg btn-block">Cadastrar com Stripe Connect</a>
                                <div class="mt-3 text-center">
                                    <a href="register.php" class="btn btn-link">Voltar para opções de cadastro</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php
        include('../common/pub.footer.php');
        exit;
    }
}

if (isset($FORM['dosubmit']) && $FORM['dosubmit'] == '1') {
    extract($FORM);

    $redirto = $_SESSION['redirto'];
    $_SESSION['redirto'] = '';

    $firstname = mystriptag($firstname);
    $lastname = mystriptag($lastname);
    $username = mystriptag($username, 'user');
    $email = mystriptag($email, 'email');

    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_invalidinput']);
        $redirval = "?res=errinstr";
        redirpageto($redirval);
        exit;
    }

    if ($cfgtoken['isdupemail'] != 1) {
        $condition = ' AND email LIKE "' . $email . '" ';
        $sql = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_mbrs WHERE 1 " . $condition . "");
        if (count($sql) > 0) {
            $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_erremailreg']);
            $redirval = "?res=emexist";
            redirpageto($redirval);
            exit;
        }
    }

    if ($cfgtoken['isautoregplan'] != 1 && $FORM['ppid'] < 1) {
        $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_errneedplan']);
        $redirval = "?res=errplanreg";
        redirpageto($redirval);
        exit;
    }

    if ($FORM['isagree'] != '1') {
        $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_termagree']);
        $redirval = "?res=erragrr";
        redirpageto($redirval);
        exit;
    }


    // reserved username
    $isunexist = is_unamereserved($username);

    // if new username exist, keep using old username
    $condition = ' AND username LIKE "' . $username . '" ';
    $sql = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_mbrs WHERE 1 " . $condition . "");

    if (!$ishcapcok && $_POST['h-captcha-response'] != '') {
        $_SESSION['show_msg'] = showalert('warning', $LANG['g_error'], $LANG['g_errorhcaptcha']);
        $redirval = "?res=hcapt";
    } elseif (!$isrecapv3 && $_POST['g-recaptcha-response'] != '') {
        $_SESSION['show_msg'] = showalert('warning', $LANG['g_error'], $LANG['g_errorrecaptcha']);
        $redirval = "?res=rcapt";
    } elseif (count($sql) > 0 || $isunexist) {
        $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_erruserexist']);
        $redirval = "?res=exist";
    } else {

        if (!dumbtoken($dumbtoken)) {
            $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_invalidtoken']);
            $redirval = "?res=errtoken";
            redirpageto($redirval);
            exit;
        }

        $in_date = date('Y-m-d H:i:s', time() + (3600 * $cfgrow['time_offset']));

        $passres = passmeter($password);
        if ($password != $passwordconfirm) {
            $_SESSION['show_msg'] = showalert('danger', $LANG['g_error'], $LANG['g_errpassnotsame']);
            $redirval = "?res=errpass";
        } elseif ($passres == 1) {
            // stages
            $stgId = intval($FORM['ppid']);
            if ($stgId < 1 || $stgId > $frlmtdcfg['mxstages']) {
                $stgId = $bpprow['ppid'];
            }
            // if manual entering sponsor
            if ($cfgtoken['ismanspruname'] == 1) {
                $sesrefcek = getmbrinfo($myspruname, 'username');
                if ($sesrefcek['mpid'] > 0) {
                    $sesref = $sesrefcek;
                    $_SESSION['ref_sess_un'] = $sesrefcek['username'];
                }
            }

            // insert new registered
            $log_ip = get_userip();
            $country = get_countrycode($log_ip);
            $hashedpassword = getpasshash($password);
            $data = array(
                'in_date' => $in_date,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'username' => $username,
                'email' => $email,
                'password' => $hashedpassword,
                'log_ip' => $log_ip,
                'country' => $country,
                'mylang' => '',
            );

            $mbrtokenval = "|refbyidmbr:{$sesref['id']}|";

            if ($cfgtoken['ismbrneedconfirm'] == 1) {
                $data['isconfirm'] = 0;
                $mbrtokenval .= ", |tempupw:" . base64_encode($passwordconfirm) . "|, |stgId:{$stgId}|";
            }
            $data['mbrtoken'] = $mbrtokenval;
            $insert = $db->insert(DB_TBLPREFIX . '_mbrs', $data);
            $newmbrid = $db->lastInsertId();

            $_SESSION['firstname'] = $_SESSION['lastname'] = $_SESSION['username'] = $_SESSION['email'] = '';

            if ($insert) {
                if ($cfgtoken['ismbrneedconfirm'] != 1) {
                    do_regandnotif($newmbrid, $stgId, $sesref['id'], $firstname . ' ' . $lastname, $passwordconfirm);
                }

                addlog_sess($username, 'member');
                $redirval = $cfgrow['site_url'] . "/" . MBRFOLDER_NAME;
            } else {
                $redirval = "?res=errsql";
            }
        } else {
            $_SESSION['show_msg'] = showalert('warning', 'Password Hint', $passres);
            $redirval = "?res=errpass";
        }
    }
    redirpageto($redirval);
    exit;
}

$modalcontent = file_get_contents(INSTALL_PATH . "/common/terms.html");
$refbystr = ($sesref['username'] != '') ? "<div class='card-header-action'><span class='badge badge-info'>| {$sesref['username']}</span></div>" : '';

$show_msg = $_SESSION['show_msg'];
$_SESSION['show_msg'] = '';

// Pega o ID de indicação (número do WhatsApp do patrocinador)
$id_indicacao = $sesref['username'] ?? '5543999300953';
?>
<section class="section">
    <div class="container" style="max-width:1200px;">
        <div class="login-brand">
            <img src="<?php echo myvalidate($site_logo); ?>" alt="logo" width="100" class="shadow-light<?php echo myvalidate($weblogo_style); ?>">
            <div><?php echo myvalidate($cfgrow['site_name']); ?></div>
        </div>

        <?php echo myvalidate($show_msg); ?>

        <!-- Opções de Cadastro -->
        <div class="cadastro-opcoes d-flex flex-wrap justify-content-center align-items-stretch">
            <!-- Cadastro WhatsApp -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch mb-4 p-0">
                <div class="card w-100 h-100 shadow-sm p-4 cadastro-card">
                    <div class="card-body text-center d-flex flex-column justify-content-center p-0">
                        <i class="fab fa-whatsapp fa-3x mb-3 text-success"></i>
                        <h5 class="mb-2 font-weight-bold">Cadastro via WhatsApp</h5>
                        <p class="text-muted">Cadastro rápido e seguro</p>
                        <a href="https://wa.me/554399300953?text=Quero%20me%20cadastrar%20no%20ID%20de%20indicação%20de%20<?php echo $id_indicacao; ?>" 
                           class="btn btn-success btn-block mt-auto font-weight-bold py-2" 
                           target="_blank">
                            Registrar com WhatsApp
                        </a>
                    </div>
                </div>
            </div>
            <!-- Cadastro Stripe Connect -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch mb-4 p-0">
                <div class="card w-100 h-100 shadow-sm p-4 cadastro-card">
                    <div class="card-body text-center d-flex flex-column justify-content-center p-0">
                        <i class="fab fa-stripe fa-3x mb-3 text-dark"></i>
                        <h5 class="mb-2 font-weight-bold">Cadastro via Stripe Connect</h5>
                        <p class="text-muted">Receba comissões globais e pagamentos instantâneos</p>
                        <a href="register.php?type=stripe<?php echo isset(
                            $idref) ? '&ref=' . urlencode($idref) : ''; ?>" class="btn btn-primary btn-block mt-auto font-weight-bold py-2">
                            Cadastrar com Stripe Connect
                        </a>
                    </div>
                </div>
            </div>
            <!-- Cadastro Telegram -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch mb-4 p-0">
                <div class="card w-100 h-100 shadow-sm p-4 cadastro-card">
                    <div class="card-body text-center d-flex flex-column justify-content-center p-0">
                        <i class="fab fa-telegram fa-3x mb-3 text-info"></i>
                        <h5 class="mb-2 font-weight-bold">Cadastro via Telegram</h5>
                        <p class="text-muted">Receba o mesmo fluxo automatizado do WhatsApp, agora também no Telegram!</p>
                        <a href="https://t.me/SeteCom1Bot" class="btn btn-info btn-block mt-auto font-weight-bold py-2" target="_blank">
                            Registrar via Telegram
                        </a>
                    </div>
                </div>
            </div>
            <!-- Cadastro Manual -->
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch mb-4 p-0">
                <div class="card w-100 h-100 shadow-sm p-4 cadastro-card">
                    <div class="card-body text-center d-flex flex-column justify-content-center p-0">
                        <i class="fas fa-user-plus fa-3x mb-3 text-primary"></i>
                        <h5 class="mb-2 font-weight-bold">Cadastro Manual</h5>
                        <p class="text-muted">Preencha o formulário com seus dados</p>
                        <button type="button" class="btn btn-primary btn-block mt-auto font-weight-bold py-2" data-toggle="collapse" data-target="#manualForm">
                            Cadastrar Manualmente
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário Manual (inicialmente oculto) -->
        <div id="manualForm" class="collapse mt-4">
            <form method="POST" class="needs-validation" id="regmbrform">
                <?php
                if ($cfgrow['join_status'] != 1) {
                    echo showalert('danger', 'Oops!', $LANG['g_noregister']);
                } elseif ($cfgrow['validref'] == 1 && $sesref['id'] < 1) {
                    echo showalert('warning', 'Oops!', $LANG['g_noreferrer']);
                } else {
                    $condition = " AND planstatus = '1' AND ppname != ''";
                    $userData = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_payplans WHERE 1" . $condition . " ORDER BY ppid LIMIT 6");
                    $planlist_content = "<input type='hidden' name='ppid' value='1'>";
                    if ($cfgtoken['isautoregplan'] == 1) {
                        if (count($userData) > 1) {
                            $planlist_content = <<<INI_HTML
                            <div class="row">
                                <div class="form-group col-12">
                                    <label class="form-label">{$LANG['g_regplanlist']}</label>
                                    <div class="selectgroup w-100">
INI_HTML;
                            foreach ($userData as $val) {
                                $intrvalstr = get_periodintervalstr($val['expday']);
                                $regamount = ($val['regfee'] > 0) ? $bpprow['currencysym'] . $val['regfee'] . ' ' . $bpprow['currencycode'] : $LANG['g_free'];
                                $doselected = ($val['ppid'] == $FORM['go']) ? ' checked' : '';
                                $planinfo = strip_tags($val['planinfo'] ?? '');
                                $pptitlepop = ($planinfo != '') ? $planinfo : $regamount . ' / ' . $intrvalstr;
                                $planlist_content .= <<<INI_HTML
                                <label class="selectgroup-item">
                                    <input type="radio" name="ppid" value="{$val['ppid']}" class="selectgroup-input"{$doselected} required>
                                    <div class="selectgroup-button" data-toggle="tooltip" title="{$pptitlepop}">{$val['ppname']}</div>
                                </label>
INI_HTML;
                            }
                            $planlist_content .= <<<INI_HTML
                            </div>
                        </div>
                    </div>
INI_HTML;
                        } else {
                            $planlist_content = "<input type='hidden' name='ppid' value='{$userData[0]['ppid']}'>";
                        }
                    }
                    ?>
                    <?php echo myvalidate($planlist_content); ?>

                    <div class="row">
                        <div class="form-group col-6">
                            <label for="firstname"><?php echo myvalidate($LANG['g_firstname']); ?></label>
                            <input id="firstname" type="text" class="form-control" name="firstname" value="<?php echo myvalidate($_SESSION['firstname']); ?>" minlength="3" autofocus required>
                            <div class="invalid-feedback">
                                <?php echo myvalidate($LANG['g_enterfirstname']); ?>
                            </div>
                        </div>
                        <div class="form-group col-6">
                            <label for="lastname"><?php echo myvalidate($LANG['g_lastname']); ?></label>
                            <input id="lastname" type="text" class="form-control" name="lastname" value="<?php echo myvalidate($_SESSION['lastname']); ?>" minlength="3" required>
                            <div class="invalid-feedback">
                                <?php echo myvalidate($LANG['g_enterlastname']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-6">
                            <label for="username">Número WhatsApp (formato internacional)</label>
                            <input id="username" type="text" class="form-control" name="username" 
                                   value="<?php echo myvalidate($_SESSION['username']); ?>" 
                                   pattern="\d{11,16}" minlength="11" maxlength="16" 
                                   placeholder="Ex: 5543999300953" 
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')" 
                                   required>
                            <div class="invalid-feedback">
                                Insira o número do WhatsApp com DDI, DDD e número (somente números).
                            </div>
                        </div>
                        <div class="form-group col-6">
                            <label for="email"><?php echo myvalidate($LANG['g_email']); ?></label>
                            <input id="email" type="email" class="form-control" name="email" value="<?php echo myvalidate($_SESSION['email']); ?>" minlength="8" required>
                            <div class="invalid-feedback">
                                <?php echo myvalidate($LANG['g_regemail']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-6">
                            <label for="password" class="d-block"><?php echo myvalidate($LANG['m_accpass']); ?></label>
                            <input id="password" type="password" class="form-control" data-indicator="pwindicator" name="password" required>
                        </div>
                        <div class="form-group col-6">
                            <label for="passwordconfirm" class="d-block"><?php echo myvalidate($LANG['m_accpassconfirm']); ?></label>
                                <input id="password2" type="password" class="form-control" name="passwordconfirm" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="isagree" value="1" class="custom-control-input" id="isagree" required>
                            <label class="custom-control-label" for="isagree"><?php echo myvalidate($LANG['g_agreeterms']); ?><a href="javascript:;" data-toggle="modal" data-target="#myModalterm"><i class="fas fa-fw fa-question-circle"></i></a></label>
                        </div>
                    </div>

                    <div class="form-group">
                        <?php echo myvalidate($ishcapcok); ?>
                        <?php echo myvalidate($isrecapv3); ?>

                        <button type='submit' class="btn btn-primary btn-lg btn-block">
                            <?php echo myvalidate($LANG['g_regbutton']); ?>
                        </button>
                        <input type="hidden" name="dosubmit" value="1">
                        <input type="hidden" name="dumbtoken" value="<?php echo myvalidate($_SESSION['dumbtoken']); ?>">
                    </div>
                    <?php
                }
                ?>
            </form>
        </div>
    </div>
</section>

<!-- Modal -->
<div class="modal fade" id="myModalterm" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo myvalidate($LANG['g_termscon']); ?></h5>
            </div>
            <div class="modal-body">
                <div class="text-muted"><?php echo myvalidate($modalcontent); ?></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-success {
    background-color: #25D366 !important;
    border-color: #25D366 !important;
}

.btn-success:hover {
    background-color: #128C7E !important;
    border-color: #128C7E !important;
}

.fa-3x {
    margin-bottom: 15px;
}

.text-success {
    color: #25D366 !important;
}

.login-brand {
    text-align: center;
    margin-bottom: 20px;
}

.login-brand img {
    display: block;
    margin-left: auto;
    margin-right: auto;
}

@media (max-width: 767.98px) {
  .cadastro-opcoes {
    display: flex;
    flex-direction: column;
  }
  .cadastro-opcoes > .col-md-6 {
    width: 100%;
    max-width: 100%;
  }
  .cadastro-opcoes > .col-md-6.order-1 {
    order: 1;
  }
  .cadastro-opcoes > .col-md-6.order-2 {
    order: 2;
  }
  .cadastro-opcoes > .col-md-6.order-3 {
    order: 3;
  }
  .cadastro-opcoes > .col-md-6.order-4 {
    order: 4;
  }
  .login-brand {
    text-align: center !important;
  }
  .login-brand img {
    display: block;
    margin-left: auto;
    margin-right: auto;
    float: none !important;
  }
}

.cadastro-opcoes {
    margin-top: 32px;
    gap: 32px;
}
.cadastro-card {
    min-width: 260px;
    max-width: 320px;
    min-height: 340px;
    margin: 0 16px;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    transition: box-shadow 0.2s;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.cadastro-card:hover {
    box-shadow: 0 8px 32px rgba(0,0,0,0.16);
}
.cadastro-card h5 {
    font-size: 1.15rem;
    letter-spacing: 0.01em;
    margin-bottom: 0.5rem;
}
.cadastro-card p {
    font-size: 0.98rem;
    margin-bottom: 1.2rem;
}
.cadastro-card .btn-block {
    font-size: 1.08em;
    border-radius: 8px;
}
@media (max-width: 991.98px) {
    .cadastro-opcoes {
        gap: 20px;
    }
    .cadastro-opcoes > .col-md-4, .cadastro-opcoes > .col-lg-3 {
        max-width: 100%;
        flex: 0 0 100%;
    }
}
@media (max-width: 767.98px) {
    .cadastro-opcoes {
        flex-direction: column !important;
        gap: 0;
    }
    .cadastro-opcoes > div {
        width: 100%;
        max-width: 100%;
        margin-bottom: 20px;
    }
    .cadastro-card {
        min-width: 100%;
        max-width: 100%;
        margin: 0;
    }
}
</style>

<?php
$_SESSION['firstname'] = $_SESSION['lastname'] = $_SESSION['username'] = $_SESSION['email'] = '';
include('../common/pub.footer.php');

function write_debug_log($msg) {
    $logfile = __DIR__ . '/stripe_register_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$timestamp] $msg\n", FILE_APPEND);
}
