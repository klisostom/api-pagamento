<?php
defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Api de pagamento
 */
class ApiPayment extends CI_Controller
{
    function __construct() 
    {
        parent::__construct(); 
    }
    
    /**
     * Use esta função para criar uma transação quando desejar especificar apenas os campos mínimos e exigir uma conversão de moeda. 
     * Por exemplo, se o preço dos seus produtos estiver em USD, mas você estiver recebendo TC, você usaria currency1 = USD e currency2 = BTC.
     */
    public function transacaoComConversao()
    {
        include_once APPPATH.'controllers/src/CoinpaymentsAPI.php';
        include_once APPPATH.'controllers/src/keys.php';

        // Create a new API wrapper instance
        $cps_api = new CoinpaymentsAPI($private_key, $public_key, 'json');

        /** 
         * O 'filter_input' retorna FALSE se o filtro falhar, ou NULL se o parâmetro é um variável não definida.
         * Em caso de sucesso retorna o valor da varável.
        */
        $amount      = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_SPECIAL_CHARS);
        $buyer_email = filter_input(INPUT_POST, 'email_comprador', FILTER_SANITIZE_SPECIAL_CHARS);
        $currency    = filter_input(INPUT_POST, 'moeda_original', FILTER_SANITIZE_SPECIAL_CHARS);
        $currency2   = filter_input(INPUT_POST, 'moeda_a_receber', FILTER_SANITIZE_SPECIAL_CHARS);
        // Não obrigatórios para a API, mas que são relevantes para a empresa.
        $address     = filter_input(INPUT_POST, 'endereco_enviar_fundos', FILTER_SANITIZE_SPECIAL_CHARS); // O endereço para o qual o comprador precisa enviar as moedas.
        $buyer_name  = filter_input(INPUT_POST, 'nome_pagador', FILTER_SANITIZE_SPECIAL_CHARS);
        $item_name   = filter_input(INPUT_POST, 'nome_item', FILTER_SANITIZE_SPECIAL_CHARS);
        $invoice     = filter_input(INPUT_POST, 'fatura', FILTER_SANITIZE_SPECIAL_CHARS);
        $ipn_url     = filter_input(INPUT_POST, 'url_retorno_chamadaIPN', FILTER_SANITIZE_SPECIAL_CHARS);
        // Define um URL para o qual o comprador conclui o pagamento.
        // Somente se você usar o 'checkout_url' retornado, não há efeito / necessidade ao criar sua própria página de checkout.
        $success_url = filter_input(INPUT_POST, 'url_sucesso_pagamento', FILTER_SANITIZE_SPECIAL_CHARS);
        // Define um URL para o qual o comprador não conclui o pagamento.
        // Somente se você usar o 'checkout_url' retornado, não há efeito / necessidade ao criar sua própria página de checkout.
        $cancel_url  = filter_input(INPUT_POST, 'url_nao_pagamento', FILTER_SANITIZE_SPECIAL_CHARS);


        // Verificando as entradas do usuário
        if (!$amount || $amount === null) {
            echo 'Quantia nao recebida. Nao foi possivel realizar a transacao';
            exit();
        }

        if (!$buyer_email || $buyer_email === null) {
            echo 'Email nao recebido. Nao foi possivel realizar a transacao';
            exit();
        }
        
        if (!$currency || $currency === null) {
            echo 'Moeda Original nao recebida. Nao foi possivel realizar a transacao';
            exit();
        }

        if (!$currency2 || $currency2 === null) {
            echo 'Moeda A Receber nao recebida. Nao foi possivel realizar a transacao';
            exit();
        }
        // Fim - Verificando as entradas do usuário

        // Fazendo a chamada a API para criar a transação
        try {
            
            $campos = [
                'amount' => $amount,
                'currency1' => $currency,
                'currency2' => $currency2,
                'buyer_email' => $buyer_email,
                'address' => $address,
                'buyer_name' => $buyer_name,
                'item_name' => $item_name,
                'invoice' => $invoice,
                'ipn_url' => $ipn_url,
                'success_url' => $success_url,
                'cancel_url' => $cancel_url
            ];
            
            $transaction_response = $cps_api->CreateCustomTransaction($campos);
        } catch (Exception $e) {
            echo json_encode(['Error: ', $e->getMessage()]);
            exit();
        }

        // Success!
        if ($transaction_response['error'] === 'ok') {
            $output = [];
            
            // ID da transação criada
            $output['id'] = $transaction_response['result']['txn_id'];
            // Valor para o comprador/pagador enviar
            $output['amount'] = $transaction_response['result']['amount'];
            /**
             * Define o endereço para o qual enviar os fundos (se não estiver definido, usará as configurações 
             * definidas na página 'Configurações de aceitação de moedas').
             * Lembre-se: este deve ser um endereço na rede da currency2.
             */
            $output['address'] = $transaction_response['result']['address'];
            /**
             * Uma URL de longo prazo em que o comprador pode visualizar o status do pagamento e deixar comentários para você. 
             * Normalmente, isso seria enviado por e-mail ao comprador.
             */
            $output['statu_pagamento'] = $transaction_response['result']['status_url'];

            /**
             * Enquanto normalmente você projetaria a experiência completa de checkout em seu site, 
             * você pode usar este URL para fornecer a página de pagamento final ao comprador.
             */
            $output['checkout_url'] = $transaction_response['result']['checkout_url'];
        } else {    
            // Caso algo tenha dado errado!
            $output['Error:'] = $transaction_response['error'];
        }

        // Retorno da chamada da API
        echo json_encode($output);
    }

    // Crie uma retirada em massa, monstrando valores diferentes para cada retirada.
    public function retiradaEmMassa()
    {
        include_once APPPATH.'controllers/src/CoinpaymentsAPI.php';
        include_once APPPATH.'controllers/src/keys.php';

        // Cria uma nova instancia da API
        $cps_api = new CoinpaymentsAPI($private_key, $public_key, 'json');

        $lote = filter_input(INPUT_POST, 'lote', FILTER_SANITIZE_SPECIAL_CHARS);
  
        $withdrawals = [];
        for ($i=0; $i < 100; $i++) {
            /*$withdrawals[$i] = [
                'amount' => 0.002,
                'currency' => 'LTCT',
                'currency2' => 'LTCT',
                'address' => 'mzYooy9d7Bbb5HFSUJGvy2QvoAM7Utudib'
            ];*/
            $withdrawals[$i] = [
                'amount' => $lote[$i]['valor'],
                'currency' => $lote[$i]['moeda_original'],
                'currency2' => $lote[$i]['moeda_a_receber'],
                'address' => $lote[$i]['endereco_enviar_fundos']
            ];
        }
     
        
        // Attempt the mass withdrawal API call
        try {
            $mass_withdrawal = $cps_api->CreateMassWithdrawal($withdrawals);
        } catch (Exception $e) {
            echo json_encode(['Error: ', $e->getMessage()]);
            exit();
        }

        // Check the result of the API call and generate a result output
        if ($mass_withdrawal["error"] === "ok") {
            $output = [];
            $count = 0;

            foreach ($mass_withdrawal['result'] as $single_withdrawal_result => $single_withdrawal_result_array) {
                if ($single_withdrawal_result_array['error'] == 'ok') {
                    $output[$count]['retirada'] = $single_withdrawal_result;
                    $output[$count]['id'] = $single_withdrawal_result_array['id'];
                    $output[$count]['status'] = $single_withdrawal_result_array['status'];
                    $output[$count]['valor'] = $single_withdrawal_result_array['amount'];
                    $count += 1;
                } else {
                    $output[$count]['retirada'] = $single_withdrawal_result;
                    $output[$count]['error'] = $single_withdrawal_result_array['error'];
                    $count += 1;
                }
            }
            
            echo json_encode($output);
        } else {
            echo json_encode($mass_withdrawal["error"]);
        }
    }
}