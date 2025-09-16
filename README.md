# 🚦 Simulador de Tráfego em PHP (MVP para Console)

Um simples, porém robusto, simulador de tráfego totalmente construído em PHP 8.3, rodando diretamente no seu terminal.

Este projeto é um MVP (Produto Mínimo Viável) que demonstra a lógica de um sistema de trânsito com regras complexas, como semáforos inteligentes, comportamento probabilístico de motoristas e detecção de colisões.

### Visualização no Console
![Exemplo de como o simulador aparece no terminal][console](https://postimg.cc/xqzh6NQB)
*Um exemplo de como o simulador aparece em execução no terminal.*

---

### ✨ Funcionalidades

* :road: **Malha Viária de Pista Dupla:** Simula uma avenida horizontal e uma vertical, cada uma com duas pistas de sentido único para um fluxo de tráfego realista.
* :traffic_light: **Semáforos Inteligentes:** Semáforos sincronizados que incluem um "tempo de proteção" (atraso) após ficarem verdes para evitar acidentes.
* :boom: **Detecção e Resolução de Colisões:** O sistema detecta conflitos de movimento, registra colisões e paralisa os veículos envolvidos, que são removidos após um tempo para liberar o trânsito.
* :game_die: **Comportamento Aleatório e Probabilístico:**
    * Veículos têm 30% de chance de virar no cruzamento.
    * Possuem 5% de chance de furar o sinal vermelho.
    * Existe 0.1% de chance de um acidente espontâneo com motivos variados (pneu furado, falha mecânica, etc.).
* :computer: **Visualização em Tempo Real:** O grid da cidade e um log de eventos são atualizados diretamente no console a cada passo da simulação.
* :wrench: **Altamente Configurável:** Todos os parâmetros da simulação (tempos, probabilidades, velocidade, etc.) podem ser ajustados facilmente em um único arquivo.

---

### ⚙️ Tecnologias Utilizadas

* **PHP 8.3**
* **Composer** (para autoloading PSR-4)

---

### 🚀 Como Executar

1.  **Clone o repositório:**
    ```bash
    git clone https://github.com/edumxk/trafic-control.git
    ```

2.  **Navegue até a pasta do projeto:**
    ```bash
    cd trafic-control
    ```

3.  **Instale as dependências** (para o autoloader):
    ```bash
    composer install
    ```

4.  **Execute a simulação:**
    ```bash
    php simular.php
    ```

---

### 🛠️ Configuração

Todos os parâmetros da simulação podem ser facilmente ajustados no topo do arquivo `simular.php`.

```php
// Exemplo de constantes em simular.php
const PASSOS_DA_SIMULACAO = 500;
const VEICULOS_INICIAIS = 20;
const TEMPO_SEMAFORO_VERDE = 30;
const TAXA_FURO_SEMAFORO = 0.01; // 1%
const VELOCIDADE_SIMULACAO = 80000; // 0.08 segundos
```

---

### 🔬 Lógica Principal

A simulação avança em "passos". Em cada passo, a lógica é dividida em fases para garantir consistência e evitar erros de simulação:

1.  **Atualização de Estado:** Semáforos e timers de colisão são atualizados. Acidentes resolvidos são limpos.
2.  **Planejamento:** Cada veículo ativo "decide" o que fazer (avançar, virar) com base nas regras do mundo (semáforos, outros veículos) e em suas próprias probabilidades.
3.  **Resolução de Conflitos e Movimentação:** O simulador analisa todos os movimentos planejados, detecta conflitos (colisões), cancela os movimentos inválidos e, por fim, move os veículos que podem avançar com segurança.
