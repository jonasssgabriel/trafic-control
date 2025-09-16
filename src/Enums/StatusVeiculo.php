<?php

namespace App\Enums;

enum StatusVeiculo: string
{
    case ATIVO = 'Ativo';
    case COLIDIDO = 'Colidido';
}