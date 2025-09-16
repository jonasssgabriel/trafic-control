<?php

require __DIR__ . '/vendor/autoload.php';

use App\Simulacao\Simulador;

// --- PAINEL DE CONTROLE DA SIMULAÇÃO ---

// -- Comportamento Geral --
const PASSOS_DA_SIMULACAO = 500;
const VEICULOS_INICIAIS = 20;

// -- Tempos do Semáforo (em número de passos/turnos) --
const TEMPO_SEMAFORO_VERDE = 30;
const TEMPO_SEMAFORO_VERMELHO = 30;
const ATRASO_SEMAFORO_VERDE = 3; 

// -- Probabilidades (de 0.0 a 1.0) --
const TAXA_FURO_SEMAFORO = 0.000;
const TAXA_COLISAO_ESPONTANEA = 0.00001;

// -- Eventos --
const TEMPO_RESOLUCAO_ACIDENTE = 20;

// -- Visualização --
const VELOCIDADE_SIMULACAO = 300000;

// --------------------------------------------------------------------

echo "Iniciando a simulação... (Pressione Ctrl+C para parar)\n";
sleep(2);

$simulador = new Simulador(
    VEICULOS_INICIAIS,
    TEMPO_SEMAFORO_VERDE,
    TEMPO_SEMAFORO_VERMELHO,
    ATRASO_SEMAFORO_VERDE,
    TAXA_FURO_SEMAFORO,
    TAXA_COLISAO_ESPONTANEA
);

$simulador->rodar(
    PASSOS_DA_SIMULACAO,
    TEMPO_RESOLUCAO_ACIDENTE,
    VELOCIDADE_SIMULACAO
);

echo "\n--- SIMULAÇÃO FINALIZADA ---\n";