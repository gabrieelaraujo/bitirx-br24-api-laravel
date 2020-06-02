# Bitrix / BR24 API - Laravel 5.8+
Laravel API connection with BR24

API feita para realizar a comunicação vinda do painel de controle dos corretores do sistema Bitrix com o site institucional, anunciando os imóveis para venda e aluguel. 

Todo o envio é feito do Bitrix => Site e nunca ao contrário, assim alimentando o site automáticamente. 


Saiba mais sobre a plataforma:
https://www.bitrix24.com.br/


## Instrução de Uso
Coloque o controller na pasta de Controllers e faça suas adaptações de acordo com sua aplicação.
Não esqueça de alterar os endpoints, exemplo: "https://site.bitrix24.com.br/rest/1/TOKEN/crm.deal.update";

Obs: A API está incluindo diversos campos personalizados pela empresa para qual a api  foi desenvolvida, basta fazer uma adaptação que irá funcionar legal.
