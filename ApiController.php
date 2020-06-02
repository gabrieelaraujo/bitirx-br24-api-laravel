<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;

class ApiController extends Controller
{
    
    public function webhook($codigo = '', $nome = '',$telefone = ''){
    
        //$token = "k/}jPGNl_Vsl-3K]2oaR~a`Y3sTw7E1xF*oS@RZ9SMpF)+TqR]lMeZNt8+|ij";

        if(empty($codigo)){
            return response()->json(['code' =>  401,'message'=> 'Código do imóvel é obrigatório. Tente novamente.'], 401);
        }

        // Validações 
        if(empty($nome) && empty($telefone)){return response()->json(['code' =>  401,'message'=> 'Nome e Telefone do corretor em branco.'], 401); }
        if(empty($nome)){ return response()->json(['code' =>  401, 'message'=> 'Nome do corretor em branco.'], 401); }
        if(empty($telefone)){  return response()->json(['code' =>  401, 'message'=> 'Telefone do corretor em branco.' ], 401); }

        $dados = Imoveis::where('codigo', $codigo)->first();
        if($dados){
            return view('desktop.api.single-imoveis', ['dados' => $dados, 'corretor' => $nome, 'telefone' => $telefone]);
        }
        return response()->json(['code' =>  401, 'message'=> 'Imóvel não encontrado. Verifique o código e tente novamente.' ], 401);
    }




    public function webhookBitrix(Request $request){
        $codigo = $request->codigo;
        $nome = $request->nome;
        $telefone = $request->telefone;
        $codigo_card = $request->codigo_card;

        // Validações 
        if(empty($codigo_card)){return response()->json(['code' =>  401,'message'=> 'Código Card é obrigatório. Tente novamente.'], 401); }
        if(empty($codigo)){return response()->json(['code' =>  401,'message'=> 'Código do imóvel é obrigatório. Tente novamente.'], 401);}  
        if(empty($nome) && empty($telefone)){return response()->json(['code' =>  401,'message'=> 'Nome e Telefone do corretor em branco.'], 401); }
        
         $url = "https://www.site.com.br/api/especial/$codigo/$nome/$telefone";        

        $endpoint = "https://site.bitrix24.com.br/rest/1/TOKEN/crm.deal.update";
        $client = new \GuzzleHttp\Client();

      
        if($url){

            $codigo = str_random(10);
            $input['link'] = $url;
            $input['code'] = $codigo;
       
            $url = "https://www.site.com.br/short/".$codigo;

            $response = $client->request('POST', $endpoint, ['query' => [
                'id' => $codigo_card,
                'fields[UF_CRM_1568906490949]' => $url
            ]]);

            Shortlink::create($input);
 
        }  
        return response()->json(['code' =>  201,'message'=> 'URL gerada com sucesso'. $url .''], 201);
    }


    /*  Check security auth
    *   Application Tokens: 
    *   Inserção: XXXX [ ONCRMCOMPANYADD ]
    *   Alteração: XXXY [ ONCRMCOMPANYUPDATE ]
    *   Exclusão: XXXYZ [ ONCRMCOMPANYDELETE ]
    */

    public function bitrixImoveis(Request $request){
        // Recebe os dados do Bitrix
        $json = array('response' => $request->all());

        //Save log status        
        Log::channel('custom')->info($json);

        $json =  json_decode(json_encode($json));    
        if(empty($json->response->auth)){response()->json(['code' => 500, 'msg' => 'Auth error not found.'], 500); }

            $token = $json->response->auth->application_token;

            if(isset($token) && $token == "XXX" || $token == "XXXY" || $token == "XXXYZ" ){

                $event = $json->response->event;
        
                switch ($event) {
                    case 'ONCRMCOMPANYADD':

                        $idImovel = $json->response->data->FIELDS->ID;

                        if(isset($idImovel)){
                            $curl_imoveis = curl_init();
                            curl_setopt($curl_imoveis, CURLOPT_URL,"https://site.bitrix24.com.br/rest/4/TOKEN/crm.company.get?id=".$idImovel);     
                            curl_setopt($curl_imoveis, CURLOPT_CONNECTTIMEOUT, 60);
                            curl_setopt($curl_imoveis, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($curl_imoveis, CURLOPT_USERAGENT, 'Bambui');
                            $getImovel = curl_exec($curl_imoveis);
                            curl_close($curl_imoveis);
                            if($getImovel !== false AND !empty($getImovel)) {                 
                                $resultadoFinal = json_decode($getImovel, true); 
                            }

                            //Remove valores em branco 
                            if(isset($resultadoFinal['result'])){
                                $resultadoFinal = array_filter($resultadoFinal['result'], function($value) {                 
                                    return !is_null($value) && $value !== '' && $value !== '0'; 
                                });
                            }           
                            
                            if(Imoveis::where('codigo', $resultadoFinal['UF_CRM_1563222693'])->exists()){
                                return response()->json(['code' => 500, 'msg' => 'Imóvel já existente. '], 500);
                            }
                            
                            // 175 => situação imóvel ativo
                            // 177 => situação inativo                            
                            if(isset($resultadoFinal['UF_CRM_1561138555533']) && $resultadoFinal['UF_CRM_1561138555533'] == '175'){    
                                $banco['id_bitrix'] = isset($resultadoFinal['ID']) ?  $resultadoFinal['ID'] : '';            
                                $banco['id_imovel_app'] = isset($resultadoFinal['UF_CRM_1564517909']) ? $resultadoFinal['UF_CRM_1564517909']  : '0';                                
                                $banco['nome'] = isset($resultadoFinal['UF_CRM_1574789119']) ?  $resultadoFinal['UF_CRM_1574789119'] : 'Sem nome';
                                $banco['codigo'] = isset($resultadoFinal['UF_CRM_1563222693']) ? $resultadoFinal['UF_CRM_1563222693'] : '00';            
                                $banco['situacao'] = (isset($resultadoFinal['UF_CRM_1561138555533']) && $resultadoFinal['UF_CRM_1561138555533'] == '175' ) ?   'Ativo' : 'Inativo';
                                $banco['disponivel_todos'] = isset($resultadoFinal['OPENED']) ? $resultadoFinal['OPENED'] : '';
                                $banco['endereco'] = isset($resultadoFinal['UF_CRM_1564519295']) ?   $resultadoFinal['UF_CRM_1564519295'] : '';
                                $banco['numero'] = isset($resultadoFinal['UF_CRM_1563218248']) ?  $resultadoFinal['UF_CRM_1563218248'] : '000000';
                                $banco['cep'] = isset($resultadoFinal['UF_CRM_1563218263']) ?  $resultadoFinal['UF_CRM_1563218263'] : '';
                                $banco['cidade'] = isset($resultadoFinal['UF_CRM_1563218429']) ?  $resultadoFinal['UF_CRM_1563218429'] : '';
                                $banco['setor'] = isset($resultadoFinal['UF_CRM_1563218449']) ?  $resultadoFinal['UF_CRM_1563218449'] : '';
                                $banco['valor_aluguel_condominio'] = isset($resultadoFinal['UF_CRM_1565979361']) ?  $resultadoFinal['UF_CRM_1565979361'] : '';
                                $banco['descricao'] = isset($resultadoFinal['UF_CRM_1563222614']) ?  utf8_encode($resultadoFinal['UF_CRM_1563222614']) : '';
            
                                if(isset($resultadoFinal['UF_CRM_1563197587188'])){
                                    if( $resultadoFinal['UF_CRM_1563197587188'] == '713'){
                                        $banco['finalidade'] = 'Residencial' ;
                                    }elseif($resultadoFinal['UF_CRM_1563197587188'] == '715'){
                                        $banco['finalidade'] = 'Comercial' ;
                                    }else{
                                        $banco['finalidade'] = 'Ambos' ;
                                    }
            
                                }
            
                                
                                $banco['valor_venda'] = isset($resultadoFinal['UF_CRM_1564060987']) ?  $resultadoFinal['UF_CRM_1564060987'] : '0.00';
                                $banco['valor_aluguel'] = isset($resultadoFinal['UF_CRM_1564061295']) ?  $resultadoFinal['UF_CRM_1564061295'] : '0.00';
                                $banco['valor_condominio'] = isset($resultadoFinal['UF_CRM_1564061375']) ?  $resultadoFinal['UF_CRM_1564061375'] : '0.00';
                                
                                if(isset($resultadoFinal['UF_CRM_1562943078'])){
                                    if( $resultadoFinal['UF_CRM_1562943078'] == '669'){
                                        $banco['objetivo'] = 'Venda' ;
                                    }                                    
                                    if($resultadoFinal['UF_CRM_1562943078'] == '671'){
                                        $banco['objetivo'] = 'Locação' ;
                                    }
                                    if($resultadoFinal['UF_CRM_1562943078'] == '3282'){                                     
                                        $banco['objetivo'] = 'Venda/Locação' ;
                                    }
                                }
                                
            
            
                                if(isset($resultadoFinal['UF_CRM_1562943275'])){
            
                                    switch ($resultadoFinal['UF_CRM_1562943275']) {
                                        case "":
                                            $tipo_imovel = '';
                                        case "677":
                                            $tipo_imovel = 'Apartamento';
                                            break;
                                        case "679":
                                            $tipo_imovel = 'Área';
                                            break;
                                        case "681":
                                            $tipo_imovel = 'Casa';
                                            break;
                                        case "683":
                                            $tipo_imovel = 'Casa em condomínio';
                                            break;
                                        case "685":
                                            $tipo_imovel = 'Chácara / Sítio';
                                            break;
                                        case "687":
                                            $tipo_imovel = 'Cobertura / Penthouse';
                                            break;            
                                        case "689":
                                            $tipo_imovel = 'Fazenda';
                                            break;     
                                        case "691":
                                            $tipo_imovel = 'Flat';
                                            break;                        
                                        case "693":
                                            $tipo_imovel = 'Galpão';
                                            break;
                                        case "695":
                                            $tipo_imovel = 'Kitnet / Loft';
                                            break;
                                        case "699":
                                            $tipo_imovel = 'Prédio comercial';
                                            break;
                                        case "697":
                                            $tipo_imovel = 'Loja';
                                            break;
                                        case "701":
                                            $tipo_imovel = 'Sala comercial';
                                            break;
                                        case "703":
                                            $tipo_imovel = 'Sobrado';
                                            break;
                                        case "705":
                                            $tipo_imovel = 'Sobrado em condomínio';
                                            break;
                                        case "707":
                                            $tipo_imovel = 'Terreno comercial';
                                            break;
                                        case "709":
                                            $tipo_imovel = 'Terreno industrial';
                                            break;
                                        case "711":
                                            $tipo_imovel = 'Terreno residencial';
                                            break;
            
                                    }
                                    $banco['tipo_imovel'] = $tipo_imovel;
                                }
                            
                    
                                //GALERIA
                                if(isset($resultadoFinal['UF_CRM_1564517909'])){
                                    $url = "https://SITE.com.br/listar_fotos_api.php?id=".$resultadoFinal['UF_CRM_1564517909'];
                                    $json = json_decode(file_get_contents($url), true);
                                    $banco['galeria'] = json_encode($json['fotos']);
                                }else{
                                    $banco['galeria'] = '';
                                }
                                        
                                if( isset($resultadoFinal['UF_CRM_1564438170']) && $resultadoFinal['UF_CRM_1564438170'] == 1){
                                    $banco['caracteristicas'][] = 'Terraço';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564438407']) && $resultadoFinal['UF_CRM_1564438407'] == 1){
                                    $banco['caracteristicas'][] = 'Hobby Box';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564438571']) && $resultadoFinal['UF_CRM_1564438571'] == 1){
                                    $banco['caracteristicas'][] = 'Garagem';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564441191']) && $resultadoFinal['UF_CRM_1564441191'] == 1){
                                    $banco['caracteristicas'][] = 'Ar Central';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564441449']) && $resultadoFinal['UF_CRM_1564441449'] == 1){
                                    $banco['caracteristicas'][] = 'Banheiro Empregada';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564441710']) && $resultadoFinal['UF_CRM_1564441710'] == 1){
                                    $banco['caracteristicas'][] = 'Dependencia Empregada';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564442209']) && $resultadoFinal['UF_CRM_1564442209'] == 1){
                                    $banco['caracteristicas'][] = 'Reformado';
                                }
                                if( isset($resultadoFinal['UF_CRM_1564442337']) && $resultadoFinal['UF_CRM_1564442337'] == 1){
                                    $banco['caracteristicas'][] = 'Home Theather';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563221533']) && $resultadoFinal['UF_CRM_1563221533'] == 1){
                                    $banco['caracteristicas'][] = $resultadoFinal['UF_CRM_1563221533'] . ' escaninho(s)';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783667240']) && $resultadoFinal['UF_CRM_1560783667240']== 1){
                                    $banco['caracteristicas'][] = 'Suíte master com closet';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560781929396']) && $resultadoFinal['UF_CRM_1560781929396']== 1){
                                    $banco['caracteristicas'][] = 'Varanda';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782678834']) && $resultadoFinal['UF_CRM_1560782678834'] == 1){
                                    $banco['caracteristicas'][] = 'Churrasqueira interna';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560781833258']) && $resultadoFinal['UF_CRM_1560781833258'] == 1){
                                    $banco['caracteristicas'][] = 'Lavabo';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560781885274']) && $resultadoFinal['UF_CRM_1560781885274'] == 1){
                                    $banco['caracteristicas'][] = 'Área de serviço';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783418200']) && $resultadoFinal['UF_CRM_1560783418200'] == 1){
                                    $banco['caracteristicas'][] = 'Ar condicionado';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783443047']) && $resultadoFinal['UF_CRM_1560783443047'] == 1){
                                    $banco['caracteristicas'][] = 'Armários planejados';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560781849962']) && $resultadoFinal['UF_CRM_1560781849962'] == 1){
                                    $banco['caracteristicas'][] = 'Banho de Serviço';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560781866906']) && $resultadoFinal['UF_CRM_1560781866906']== 1){
                                    $banco['caracteristicas'][] = 'Quarto de Serviço';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783716655']) && $resultadoFinal['UF_CRM_1560783716655']== 1){
                                    $banco['caracteristicas'][] = 'Escritório';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222197']) && $resultadoFinal['UF_CRM_1563222197']== 1){
                                    $banco['caracteristicas'][] = 'Adega';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222214']) && $resultadoFinal['UF_CRM_1563222214'] == 1){
                                    $banco['caracteristicas'][] = 'Quintal';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222239']) && $resultadoFinal['UF_CRM_1563222239'] == 1){
                                    $banco['caracteristicas'][] = 'Cozinha americana';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222296']) && $resultadoFinal['UF_CRM_1563222296'] == 1){
                                    $banco['caracteristicas'][] = 'Despensa';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222311']) && $resultadoFinal['UF_CRM_1563222311'] == 1){
                                    $banco['caracteristicas'][] = 'Aceita PET';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222336']) && $resultadoFinal['UF_CRM_1563222336'] == 1){
                                    $banco['caracteristicas'][] = 'Alarme';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222369']) && $resultadoFinal['UF_CRM_1563222369'] == 1){
                                    $banco['caracteristicas'][] = 'Edícula';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222387']) && $resultadoFinal['UF_CRM_1563222387'] == 1){
                                    $banco['caracteristicas'][] = 'Semi mobiliado';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222402']) && $resultadoFinal['UF_CRM_1563222402'] == 1){
                                    $banco['caracteristicas'][] = 'Mobiliado';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222419']) && $resultadoFinal['UF_CRM_1563222419'] == 1){
                                    $banco['caracteristicas'][] = 'Hidromassagem';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782128859'])){
                                    $banco['metragem'] = $resultadoFinal['UF_CRM_1560782128859'] . 'm²';
                                }              
                                if( isset($resultadoFinal['UF_CRM_1560782548019']) && $resultadoFinal['UF_CRM_1560782548019'] == 1){
                                    $banco['caracteristicas'][] = 'Piscina';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782748841']) && $resultadoFinal['UF_CRM_1560782748841'] == 1){
                                    $banco['caracteristicas'][] = 'Piscina adulto aquecida';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782718073']) && $resultadoFinal['UF_CRM_1560782718073'] == 1){
                                    $banco['caracteristicas'][] = 'Piscina infantil';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782765521']) && $resultadoFinal['UF_CRM_1560782765521'] == 1){
                                    $banco['caracteristicas'][] = 'Piscina infantil aquecida';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782836753']) && $resultadoFinal['UF_CRM_1560782836753'] == 1){
                                    $banco['caracteristicas'][] = 'Sauna';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782858818']) && $resultadoFinal['UF_CRM_1560782858818']== 1){
                                    $banco['caracteristicas'][] = 'SPA';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782562610']) && $resultadoFinal['UF_CRM_1560782562610'] == 1){
                                    $banco['caracteristicas'][] = 'Salão de Festas';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782574226']) && $resultadoFinal['UF_CRM_1560782574226']== 1){
                                    $banco['caracteristicas'][] = 'Espaço Gourmet';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560781944794']) && $resultadoFinal['UF_CRM_1560781944794'] == 1){
                                    $banco['caracteristicas'][] = 'Churrasqueira';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782664650']) && $resultadoFinal['UF_CRM_1560782664650'] == 1){
                                    $banco['caracteristicas'][] = 'Brinquedoteca';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782791802']) && $resultadoFinal['UF_CRM_1560782791802'] == 1){
                                    $banco['caracteristicas'][] = 'Playground';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782955178'])  && $resultadoFinal['UF_CRM_1560782955178']== 1){
                                    $banco['caracteristicas'][] = 'Sala de jogos';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782876033']) && $resultadoFinal['UF_CRM_1560782876033'] == 1){
                                    $banco['caracteristicas'][] = 'Quadra';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782906441']) && $resultadoFinal['UF_CRM_1560782906441'] == 1){
                                    $banco['caracteristicas'][] = 'Espaço Mulher';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782925770']) && $resultadoFinal['UF_CRM_1560782925770'] == 1){
                                    $banco['caracteristicas'][] = 'Academia';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560782645713']) && $resultadoFinal['UF_CRM_1560782645713'] == 1){
                                    $banco['caracteristicas'][] = 'Bicicletário';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783006074']) && $resultadoFinal['UF_CRM_1560783006074'] == 1){
                                    $banco['caracteristicas'][] = 'Rooftop';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783042274']) && $resultadoFinal['UF_CRM_1560783042274'] == 1){
                                    $banco['caracteristicas'][] = 'Interfone';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783053977']) && $resultadoFinal['UF_CRM_1560783053977'] == 1){
                                    $banco['caracteristicas'][] = 'Portaria remota';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783068953']) && $resultadoFinal['UF_CRM_1560783068953'] == 1){
                                    $banco['caracteristicas'][] = 'Portaria tradicional';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563222097']) && $resultadoFinal['UF_CRM_1563222097'] == 1){
                                    $banco['caracteristicas'][] = 'Portão eletrônico';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783094922']) && $resultadoFinal['UF_CRM_1560783094922'] == 1){
                                    $banco['caracteristicas'][] = 'CFTV';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783115386']) && $resultadoFinal['UF_CRM_1560783115386'] == 1){
                                    $banco['caracteristicas'][] = 'Elevador de Serviço';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783134433']) && $resultadoFinal['UF_CRM_1560783134433'] == 1){
                                    $banco['caracteristicas'][] = 'Elevador Social';
                                }
                                if( isset($resultadoFinal['UF_CRM_1563223230']) && $resultadoFinal['UF_CRM_1563223230'] == 1){
                                    $banco['caracteristicas'][] = 'Quantidade de elevadores';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783267072']) && $resultadoFinal['UF_CRM_1560783267072'] == 1){
                                    $banco['caracteristicas'][] = 'Central de Gás';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783187520']) && $resultadoFinal['UF_CRM_1560783187520'] == 1){
                                    $banco['caracteristicas'][] = 'Hidrômetro individualizado';
                                }
                                if( isset($resultadoFinal['UF_CRM_1560783216201']) && $resultadoFinal['UF_CRM_1560783216201'] == 1){
                                    $banco['caracteristicas'][] = 'Medidor de energia individualizado';
                                }
                    
                                if(isset($banco['caracteristicas'])){           
                                    $banco['caracteristicas'] = implode(", ",$banco['caracteristicas']);
                                }else{
                                    $banco['caracteristicas'] = 'Nenhuma';
                                }
                                //Quantidade de Quartos
                                if( isset($resultadoFinal['UF_CRM_1563221042'])){
                                    $banco['qtd_quartos'] = $resultadoFinal['UF_CRM_1563221042'];
                                }
            
                                //Quantidade de Suítes
                                if( isset($resultadoFinal['UF_CRM_1563221024'])){
                                    $banco['qtd_suites'] = $resultadoFinal['UF_CRM_1563221024'];
                                }
            
                                //Quantidade de Suítes
                                if( isset($resultadoFinal['UF_CRM_1563220999'])){
                                    $banco['qtd_banheiros'] = $resultadoFinal['UF_CRM_1563220999'];
                                }
            
                                //Quantidade de Vagas
                                if( isset($resultadoFinal['UF_CRM_1560781390987']) ){
                                    $banco['qtd_vagas'] = $resultadoFinal['UF_CRM_1560781390987'];
                                }


                                if(isset($banco)){     
                                    Log::channel('custom')->info('Imóvel adicionado com sucesso.');
                                    Log::channel('custom')->debug($banco);
                                    
                                    
                                   // Log::info('bitrix-created.log', $banco);                   
                                    // ADICIONA NO BANCO                         
                                    $imovel = Imoveis::create($banco);          
                                    
                                    if($imovel){
                                        return response()->json(['code' => 200, 'msg' => 'Imóvel adicionado com sucesso.'], 200);
                                    }else{
                                        return response()->json(['code' => 500, 'msg' => 'Erro ao adicionar imóvel.'], 500);
                                    }
                                    
                                }



                            }else{
                                return response()->json(['code' => 500, 'msg' => 'Error ao adicionar. Imóvel inativo. '], 500);
                            }    // end if inativo

                        }else{

                            return response()->json(['code' => 500, 'msg' => 'ID not found'], 500);

                        } //end check ID exist

                    break;

                    case 'ONCRMCOMPANYUPDATE':
                        
                        $idImovel = $json->response->data->FIELDS->ID;

                        if(isset($idImovel)){
                            $curl_imoveis = curl_init();
                            curl_setopt($curl_imoveis, CURLOPT_URL,"https://SITE.bitrix24.com.br/rest/4/TOKEN/crm.company.get?id=".$idImovel);     
                            curl_setopt($curl_imoveis, CURLOPT_CONNECTTIMEOUT, 60);
                            curl_setopt($curl_imoveis, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($curl_imoveis, CURLOPT_USERAGENT, 'Bambui');
                            $getImovel = curl_exec($curl_imoveis);
                            curl_close($curl_imoveis);
                            if($getImovel !== false AND !empty($getImovel)) {                 
                                $resultadoFinal = json_decode($getImovel, true); 
                            }

                            //Remove valores em branco 
                            if(isset($resultadoFinal['result'])){
                                $resultadoFinal = array_filter($resultadoFinal['result'], function($value) {                 
                                    return !is_null($value) && $value !== '' && $value !== '0'; 
                                });
                            }            
                            
                                       
                                        
                            $banco['id_bitrix'] = isset($resultadoFinal['ID']) ?  $resultadoFinal['ID'] : '';            
                            $banco['id_imovel_app'] = isset($resultadoFinal['UF_CRM_1564517909']) ? $resultadoFinal['UF_CRM_1564517909']  : '0';
                            $banco['nome'] = isset($resultadoFinal['UF_CRM_1574789119']) ?  $resultadoFinal['UF_CRM_1574789119'] : 'Sem nome';
                            $banco['codigo'] = isset($resultadoFinal['UF_CRM_1563222693']) ? $resultadoFinal['UF_CRM_1563222693'] : '00';            
                            $banco['situacao'] = (isset($resultadoFinal['UF_CRM_1561138555533']) && $resultadoFinal['UF_CRM_1561138555533'] == '175' ) ?   'Ativo' : 'Inativo';
                            $banco['disponivel_todos'] = isset($resultadoFinal['OPENED']) ? $resultadoFinal['OPENED'] : '';
                            $banco['endereco'] = isset($resultadoFinal['UF_CRM_1564519295']) ?   $resultadoFinal['UF_CRM_1564519295'] : '';
                            $banco['numero'] = isset($resultadoFinal['UF_CRM_1563218248']) ?  $resultadoFinal['UF_CRM_1563218248'] : '000000';
                            $banco['cep'] = isset($resultadoFinal['UF_CRM_1563218263']) ?  $resultadoFinal['UF_CRM_1563218263'] : '';
                            $banco['cidade'] = isset($resultadoFinal['UF_CRM_1563218429']) ?  $resultadoFinal['UF_CRM_1563218429'] : '';
                            $banco['setor'] = isset($resultadoFinal['UF_CRM_1563218449']) ?  $resultadoFinal['UF_CRM_1563218449'] : '';
                            $banco['valor_aluguel_condominio'] = isset($resultadoFinal['UF_CRM_1565979361']) ?  $resultadoFinal['UF_CRM_1565979361'] : '';
                            $banco['descricao'] = isset($resultadoFinal['UF_CRM_1563222614']) ?  utf8_encode($resultadoFinal['UF_CRM_1563222614']) : '';
        
                            if(isset($resultadoFinal['UF_CRM_1563197587188'])){
                                if( $resultadoFinal['UF_CRM_1563197587188'] == '713'){
                                    $banco['finalidade'] = 'Residencial' ;
                                }elseif($resultadoFinal['UF_CRM_1563197587188'] == '715'){
                                    $banco['finalidade'] = 'Comercial' ;
                                }else{
                                    $banco['finalidade'] = 'Ambos' ;
                                }
        
                            }
        
                            
                            $banco['valor_venda'] = isset($resultadoFinal['UF_CRM_1564060987']) ?  $resultadoFinal['UF_CRM_1564060987'] : '0.00';
                            $banco['valor_aluguel'] = isset($resultadoFinal['UF_CRM_1564061295']) ?  $resultadoFinal['UF_CRM_1564061295'] : '0.00';
                            $banco['valor_condominio'] = isset($resultadoFinal['UF_CRM_1564061375']) ?  $resultadoFinal['UF_CRM_1564061375'] : '0.00';
                            
                            if(isset($resultadoFinal['UF_CRM_1562943078'])){
                                if( $resultadoFinal['UF_CRM_1562943078'] == '669'){
                                    $banco['objetivo'] = 'Venda' ;
                                }elseif($resultadoFinal['UF_CRM_1562943078'] == '671'){
                                    $banco['objetivo'] = 'Locação' ;
                                }
                            }
        
        
                            if(isset($resultadoFinal['UF_CRM_1562943275'])){
        
                                switch ($resultadoFinal['UF_CRM_1562943275']) {
                                    case "":
                                        $tipo_imovel = '';
                                    case "677":
                                        $tipo_imovel = 'Apartamento';
                                        break;
                                    case "679":
                                        $tipo_imovel = 'Área';
                                        break;
                                    case "681":
                                        $tipo_imovel = 'Casa';
                                        break;
                                    case "683":
                                        $tipo_imovel = 'Casa em condomínio';
                                        break;
                                    case "685":
                                        $tipo_imovel = 'Chácara / Sítio';
                                        break;
                                    case "687":
                                        $tipo_imovel = 'Cobertura / Penthouse';
                                        break;            
                                    case "689":
                                        $tipo_imovel = 'Fazenda';
                                        break;     
                                    case "691":
                                        $tipo_imovel = 'Flat';
                                        break;                        
                                    case "693":
                                        $tipo_imovel = 'Galpão';
                                        break;
                                    case "695":
                                        $tipo_imovel = 'Kitnet / Loft';
                                        break;
                                    case "699":
                                        $tipo_imovel = 'Prédio comercial';
                                        break;
                                    case "697":
                                        $tipo_imovel = 'Loja';
                                        break;
                                    case "701":
                                        $tipo_imovel = 'Sala comercial';
                                        break;
                                    case "703":
                                        $tipo_imovel = 'Sobrado';
                                        break;
                                    case "705":
                                        $tipo_imovel = 'Sobrado em condomínio';
                                        break;
                                    case "707":
                                        $tipo_imovel = 'Terreno comercial';
                                        break;
                                    case "709":
                                        $tipo_imovel = 'Terreno industrial';
                                        break;
                                    case "711":
                                        $tipo_imovel = 'Terreno residencial';
                                        break;
        
                                }
                                $banco['tipo_imovel'] = $tipo_imovel;
                            }
                        
                
                            //GALERIA
                            if(isset($resultadoFinal['UF_CRM_1564517909'])){
                                $url = "https://site.com.br/listar_fotos_api.php?id=".$resultadoFinal['UF_CRM_1564517909'];
                                $json = json_decode(file_get_contents($url), true); 
                                $banco['galeria'] = json_encode($json['fotos']);
                            }else{
                                $banco['galeria'] = '';
                            }
                                    
                            if( isset($resultadoFinal['UF_CRM_1564438170']) && $resultadoFinal['UF_CRM_1564438170'] == 1){
                                $banco['caracteristicas'][] = 'Terraço';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564438407']) && $resultadoFinal['UF_CRM_1564438407'] == 1){
                                $banco['caracteristicas'][] = 'Hobby Box';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564438571']) && $resultadoFinal['UF_CRM_1564438571'] == 1){
                                $banco['caracteristicas'][] = 'Garagem';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564441191']) && $resultadoFinal['UF_CRM_1564441191'] == 1){
                                $banco['caracteristicas'][] = 'Ar Central';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564441449']) && $resultadoFinal['UF_CRM_1564441449'] == 1){
                                $banco['caracteristicas'][] = 'Banheiro Empregada';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564441710']) && $resultadoFinal['UF_CRM_1564441710'] == 1){
                                $banco['caracteristicas'][] = 'Dependencia Empregada';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564442209']) && $resultadoFinal['UF_CRM_1564442209'] == 1){
                                $banco['caracteristicas'][] = 'Reformado';
                            }
                            if( isset($resultadoFinal['UF_CRM_1564442337']) && $resultadoFinal['UF_CRM_1564442337'] == 1){
                                $banco['caracteristicas'][] = 'Home Theather';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563221533']) && $resultadoFinal['UF_CRM_1563221533'] == 1){
                                $banco['caracteristicas'][] = $resultadoFinal['UF_CRM_1563221533'] . ' escaninho(s)';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783667240']) && $resultadoFinal['UF_CRM_1560783667240']== 1){
                                $banco['caracteristicas'][] = 'Suíte master com closet';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560781929396']) && $resultadoFinal['UF_CRM_1560781929396']== 1){
                                $banco['caracteristicas'][] = 'Varanda';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782678834']) && $resultadoFinal['UF_CRM_1560782678834'] == 1){
                                $banco['caracteristicas'][] = 'Churrasqueira interna';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560781833258']) && $resultadoFinal['UF_CRM_1560781833258'] == 1){
                                $banco['caracteristicas'][] = 'Lavabo';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560781885274']) && $resultadoFinal['UF_CRM_1560781885274'] == 1){
                                $banco['caracteristicas'][] = 'Área de serviço';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783418200']) && $resultadoFinal['UF_CRM_1560783418200'] == 1){
                                $banco['caracteristicas'][] = 'Ar condicionado';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783443047']) && $resultadoFinal['UF_CRM_1560783443047'] == 1){
                                $banco['caracteristicas'][] = 'Armários planejados';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560781849962']) && $resultadoFinal['UF_CRM_1560781849962'] == 1){
                                $banco['caracteristicas'][] = 'Banho de Serviço';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560781866906']) && $resultadoFinal['UF_CRM_1560781866906']== 1){
                                $banco['caracteristicas'][] = 'Quarto de Serviço';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783716655']) && $resultadoFinal['UF_CRM_1560783716655']== 1){
                                $banco['caracteristicas'][] = 'Escritório';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222197']) && $resultadoFinal['UF_CRM_1563222197']== 1){
                                $banco['caracteristicas'][] = 'Adega';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222214']) && $resultadoFinal['UF_CRM_1563222214'] == 1){
                                $banco['caracteristicas'][] = 'Quintal';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222239']) && $resultadoFinal['UF_CRM_1563222239'] == 1){
                                $banco['caracteristicas'][] = 'Cozinha americana';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222296']) && $resultadoFinal['UF_CRM_1563222296'] == 1){
                                $banco['caracteristicas'][] = 'Despensa';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222311']) && $resultadoFinal['UF_CRM_1563222311'] == 1){
                                $banco['caracteristicas'][] = 'Aceita PET';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222336']) && $resultadoFinal['UF_CRM_1563222336'] == 1){
                                $banco['caracteristicas'][] = 'Alarme';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222369']) && $resultadoFinal['UF_CRM_1563222369'] == 1){
                                $banco['caracteristicas'][] = 'Edícula';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222387']) && $resultadoFinal['UF_CRM_1563222387'] == 1){
                                $banco['caracteristicas'][] = 'Semi mobiliado';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222402']) && $resultadoFinal['UF_CRM_1563222402'] == 1){
                                $banco['caracteristicas'][] = 'Mobiliado';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222419']) && $resultadoFinal['UF_CRM_1563222419'] == 1){
                                $banco['caracteristicas'][] = 'Hidromassagem';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782128859'])){
                                $banco['metragem'] = $resultadoFinal['UF_CRM_1560782128859'] . 'm²';
                            } 
                            
                            if( isset($resultadoFinal['UF_CRM_1560782548019']) && $resultadoFinal['UF_CRM_1560782548019'] == 1){
                                $banco['caracteristicas'][] = 'Piscina';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782748841']) && $resultadoFinal['UF_CRM_1560782748841'] == 1){
                                $banco['caracteristicas'][] = 'Piscina adulto aquecida';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782718073']) && $resultadoFinal['UF_CRM_1560782718073'] == 1){
                                $banco['caracteristicas'][] = 'Piscina infantil';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782765521']) && $resultadoFinal['UF_CRM_1560782765521'] == 1){
                                $banco['caracteristicas'][] = 'Piscina infantil aquecida';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782836753']) && $resultadoFinal['UF_CRM_1560782836753'] == 1){
                                $banco['caracteristicas'][] = 'Sauna';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782858818']) && $resultadoFinal['UF_CRM_1560782858818']== 1){
                                $banco['caracteristicas'][] = 'SPA';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782562610']) && $resultadoFinal['UF_CRM_1560782562610'] == 1){
                                $banco['caracteristicas'][] = 'Salão de Festas';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782574226']) && $resultadoFinal['UF_CRM_1560782574226']== 1){
                                $banco['caracteristicas'][] = 'Espaço Gourmet';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560781944794']) && $resultadoFinal['UF_CRM_1560781944794'] == 1){
                                $banco['caracteristicas'][] = 'Churrasqueira';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782664650']) && $resultadoFinal['UF_CRM_1560782664650'] == 1){
                                $banco['caracteristicas'][] = 'Brinquedoteca';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782791802']) && $resultadoFinal['UF_CRM_1560782791802'] == 1){
                                $banco['caracteristicas'][] = 'Playground';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782955178'])  && $resultadoFinal['UF_CRM_1560782955178']== 1){
                                $banco['caracteristicas'][] = 'Sala de jogos';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782876033']) && $resultadoFinal['UF_CRM_1560782876033'] == 1){
                                $banco['caracteristicas'][] = 'Quadra';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782906441']) && $resultadoFinal['UF_CRM_1560782906441'] == 1){
                                $banco['caracteristicas'][] = 'Espaço Mulher';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782925770']) && $resultadoFinal['UF_CRM_1560782925770'] == 1){
                                $banco['caracteristicas'][] = 'Academia';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560782645713']) && $resultadoFinal['UF_CRM_1560782645713'] == 1){
                                $banco['caracteristicas'][] = 'Bicicletário';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783006074']) && $resultadoFinal['UF_CRM_1560783006074'] == 1){
                                $banco['caracteristicas'][] = 'Rooftop';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783042274']) && $resultadoFinal['UF_CRM_1560783042274'] == 1){
                                $banco['caracteristicas'][] = 'Interfone';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783053977']) && $resultadoFinal['UF_CRM_1560783053977'] == 1){
                                $banco['caracteristicas'][] = 'Portaria remota';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783068953']) && $resultadoFinal['UF_CRM_1560783068953'] == 1){
                                $banco['caracteristicas'][] = 'Portaria tradicional';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563222097']) && $resultadoFinal['UF_CRM_1563222097'] == 1){
                                $banco['caracteristicas'][] = 'Portão eletrônico';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783094922']) && $resultadoFinal['UF_CRM_1560783094922'] == 1){
                                $banco['caracteristicas'][] = 'CFTV';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783115386']) && $resultadoFinal['UF_CRM_1560783115386'] == 1){
                                $banco['caracteristicas'][] = 'Elevador de Serviço';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783134433']) && $resultadoFinal['UF_CRM_1560783134433'] == 1){
                                $banco['caracteristicas'][] = 'Elevador Social';
                            }
                            if( isset($resultadoFinal['UF_CRM_1563223230']) && $resultadoFinal['UF_CRM_1563223230'] == 1){
                                $banco['caracteristicas'][] = 'Quantidade de elevadores';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783267072']) && $resultadoFinal['UF_CRM_1560783267072'] == 1){
                                $banco['caracteristicas'][] = 'Central de Gás';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783187520']) && $resultadoFinal['UF_CRM_1560783187520'] == 1){
                                $banco['caracteristicas'][] = 'Hidrômetro individualizado';
                            }
                            if( isset($resultadoFinal['UF_CRM_1560783216201']) && $resultadoFinal['UF_CRM_1560783216201'] == 1){
                                $banco['caracteristicas'][] = 'Medidor de energia individualizado';
                            }
                
                            if(isset($banco['caracteristicas'])){           
                                $banco['caracteristicas'] = implode(", ",$banco['caracteristicas']);
                            }else{
                                $banco['caracteristicas'] = 'Nenhuma';
                            }

                            //Quantidade de Quartos
                            if( isset($resultadoFinal['UF_CRM_1563221042'])){
                                $banco['qtd_quartos'] = $resultadoFinal['UF_CRM_1563221042'];
                            }
        
                            //Quantidade de Suítes
                            if( isset($resultadoFinal['UF_CRM_1563221024'])){
                                $banco['qtd_suites'] = $resultadoFinal['UF_CRM_1563221024'];
                            }
        
                            //Quantidade de Suítes
                            if( isset($resultadoFinal['UF_CRM_1563220999'])){
                                $banco['qtd_banheiros'] = $resultadoFinal['UF_CRM_1563220999'];
                            }
        
                            //Quantidade de Vagas
                            if( isset($resultadoFinal['UF_CRM_1560781390987']) ){
                                $banco['qtd_vagas'] = $resultadoFinal['UF_CRM_1560781390987'];
                            }


                            if(isset($banco)){     
                                Log::channel('custom')->info('Imóvel atualizado com sucesso.');
                                Log::channel('custom')->debug($banco);          

                                $codigoImovel = $resultadoFinal['UF_CRM_1563222693'];

                                if(isset($codigoImovel)){
                                    
                                    //Verifica se existe o imóvel no banco para atualização
                                    if ( Imoveis::where('codigo', $codigoImovel)->count() > 0) {
                                        // Atualiza o imóvel                     
                                        $update = Imoveis::where('codigo', $codigoImovel)->first();
                                        $update->fill($banco);
                                        $update->save();
                                        
                                        if($update){
                                            return response()->json(['code' => 200, 'msg' => 'Imóvel atualizado com sucesso.'], 200);
                                        }else{
                                            return response()->json(['code' => 500, 'msg' => 'Erro ao adicionar imóvel.'], 500);
                                        }

                                        //Se o imóvel não existe, faz o cadastro dele.
                                    }else{
                                        Log::channel('custom')->info('Tentativa de atualização em imóvel não cadastrado.');

                                        $imovel = Imoveis::create($banco);          
                                    
                                        if($imovel){
                                            return response()->json(['code' => 200, 'msg' => 'Imóvel adicionado com sucesso.'], 200);
                                        }else{
                                            return response()->json(['code' => 500, 'msg' => 'Erro ao adicionar imóvel.'], 500);
                                        }
                                    }
                                    
                                }else{
                                    return response()->json(['code' => 500, 'msg' => 'Code not found'], 500);
                                }
                                
                            }


                        }else{

                            return response()->json(['code' => 500, 'msg' => 'ID not found'], 500);

                        } //end check ID exist


                    break;

                    case 'ONCRMCOMPANYDELETE':
                    break;
                }

            }else{
                return response()->json(['code' => 500, 'msg' => 'Invalid token.'], 500);              
            }
    }


}
