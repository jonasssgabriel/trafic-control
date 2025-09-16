<?php

require __DIR__ . '/vendor/autoload.php';

use App\Simulacao\Simulador;

// --- PARÂMETROS DA SIMULAÇÃO ---

// Por quantos "passos" ou "turnos" a simulação vai rodar.
const PASSOS_DA_SIMULACAO = 50;

// Quantos veículos começarão na simulação.
const VEICULOS_INICIAIS = 5;

// Por quantos passos um acidente bloqueia a via.
const TEMPO_RESOLUCAO_ACIDENTE = 20;


// ------------------------------------
echo "Iniciando a simulação... (Pressione Ctrl+C para parar)\n";
sleep(2);


$simulador = new Simulador(VEICULOS_INICIAIS);
$simulador->rodar(PASSOS_DA_SIMULACAO, TEMPO_RESOLUCAO_ACIDENTE);


echo "\n--- SIMULAÇÃO FINALIZADA ---\n";