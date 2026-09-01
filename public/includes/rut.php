<?php

function normalizarRut(string $rut): string
{
    $limpio = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));
    if (strlen($limpio) < 2) {
        return '';
    }

    return substr($limpio, 0, -1) . '-' . substr($limpio, -1);
}

function validarRut(string $rut): bool
{
    $normalizado = normalizarRut($rut);
    if ($normalizado === '') {
        return false;
    }

    [$cuerpo, $dv] = explode('-', $normalizado, 2);
    return preg_match('/^[0-9]{6,8}$/', $cuerpo) === 1
        && preg_match('/^[0-9K]$/', $dv) === 1;
}

function formatearRut(string $rut): string
{
    $normalizado = normalizarRut($rut);
    if ($normalizado === '') {
        return '';
    }

    [$cuerpo, $dv] = explode('-', $normalizado, 2);
    return number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;
}
