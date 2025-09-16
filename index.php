<?php
// PARTE 1: LÓGICA PHP PARA GERAR OS DADOS
require __DIR__ . '/vendor/autoload.php';
use App\Simulacao\Simulador;

// Suas constantes de configuração
const PASSOS_DA_SIMULACAO = 500;
const VEICULOS_INICIAIS = 15;
const TEMPO_SEMAFORO_VERDE = 30;
const TEMPO_SEMAFORO_VERMELHO = 30;
const ATRASO_SEMAFORO_VERDE = 3;
const TAXA_FURO_SEMAFORO = 0.01;
const TAXA_COLISAO_ESPONTANEA = 0.001;
const TEMPO_RESOLUCAO_ACIDENTE = 20;
const VELOCIDADE_SIMULACAO_HTML = 100; // Deixei mais rápido para melhor visualização (80ms)

$simulador = new Simulador(
    VEICULOS_INICIAIS, TEMPO_SEMAFORO_VERDE, TEMPO_SEMAFORO_VERMELHO,
    ATRASO_SEMAFORO_VERDE, TAXA_FURO_SEMAFORO, TAXA_COLISAO_ESPONTANEA
);

// --- LÓGICA DE GERAÇÃO DE FRAMES CORRIGIDA ---
$frames = [];
for ($i = 1; $i <= PASSOS_DA_SIMULACAO; $i++) {
    // Avança o estado interno do simulador em um passo
    $simulador->_executarPasso(TEMPO_RESOLUCAO_ACIDENTE);
    
    // Agora, renderiza e captura a saída do novo estado
    ob_start();
    $simulador->_desenharGridNoConsole();
    $frames[] = ob_get_contents();
    ob_end_clean();
}

$framesJson = json_encode($frames);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Simulador de Tráfego</title>
    <style>
        body {
            font-family: 'Consolas', 'Courier New', Courier, monospace;
            background-color: #1e1e1e;
            color: #d4d4d4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        #simulador-container {
            padding: 1em 2em;
            background-color: #252526;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.5);
            /* Layout fixo para evitar que a tela "pule" */
            width: 80vw;
            max-width: 1000px;
        }
        pre {
            margin: 0;
            font-size: 14px;
            line-height: 1.2;
            white-space: pre; /* Garante que o texto não quebre linha */
        }
        #grid-container {
            border-bottom: 2px solid #444;
            margin-bottom: 1em;
            padding-bottom: 1em;
        }
        #log-container {
            /* Define uma altura mínima para caber o título + 10 linhas de log */
            min-height: 12em; 
        }
    </style>
</head>
<body>

    <div id="simulador-container">
        <div id="grid-container">
            <pre id="grid-output"></pre>
        </div>
        <div id="log-container">
            <pre id="log-output"></pre>
        </div>
    </div>

    <script>
        const frames = <?= $framesJson ?>;
        const totalPassos = <?= PASSOS_DA_SIMULACAO ?>;
        const velocidade = <?= VELOCIDADE_SIMULACAO_HTML ?>;

        const gridOutputElement = document.getElementById('grid-output');
        const logOutputElement = document.getElementById('log-output');
        let passoAtual = 0;

        function exibirPasso() {
            if (!frames[passoAtual]) return;

            // Pega o frame de texto completo gerado pelo PHP
            let frameCompleto = frames[passoAtual].replace(/\[H\[J/g, '');

            // --- LÓGICA DE SEPARAÇÃO DO CONTEÚDO ---
            // Divide o frame em duas partes usando o título do log como separador
            const separador = "--- Histórico de Eventos";
            const partes = frameCompleto.split(separador);

            const parteGrid = partes[0] || '';
            const parteLog = separador + (partes[1] || ' (últimos 10) ---\nNenhum evento registrado ainda.\n');

            // Atualiza os elementos HTML separadamente
            gridOutputElement.textContent = parteGrid;
            logOutputElement.textContent = parteLog;

            passoAtual++;
            if (passoAtual >= totalPassos) {
                passoAtual = 0; // Reinicia a animação
            }
        }
        
        console.log(`Iniciando animação com ${frames.length} frames.`);
        setInterval(exibirPasso, velocidade);
    </script>

</body>
</html>