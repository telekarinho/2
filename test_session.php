<?php
session_start();
if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = rand(1000,9999);
    echo 'Nova sessão criada: ' . $_SESSION['test'];
} else {
    echo 'Sessão existente: ' . $_SESSION['test'];
}