<?php

function prefijoPorEspecie(string $especie): string
{
    $clave = mb_strtolower(trim($especie));
    return match ($clave) {
        'perro' => 'P',
        'gato' => 'G',
        'ave' => 'A',
        'conejo' => 'C',
        default => 'O',
    };
}
function siguienteIdentificadorMascota(PDO $pdo, string $especie): string
{
    $prefijo = prefijoPorEspecie($especie);
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(identificador, '-', -1) AS UNSIGNED))
        FROM mascotas WHERE identificador REGEXP :patron");
    $stmt->execute([':patron' => '^' . $prefijo . '-[0-9]+$']);
    $numero = (int)$stmt->fetchColumn() + 1;
    return $prefijo . '-' . str_pad((string)$numero, 3, '0', STR_PAD_LEFT);
}
