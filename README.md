## mspVictoriabankmd

Card payment method by Victoriabank.md (Moldova) for miniShop2

1) Создать привытный ключ с помощью OpenSSL
openssl genrsa -f4 -out key.pem 2048

Этот ключ, после установки модуля, поместить в папку 'components/minishop2/custom/payment/lib/victoriabankmd/

Там же должен уже лежить публичный ключ от банка victoria_pub.pem

2) Создать публичный ключ
openssl rsa -pubout -in key.pem -out pubkey.pem

Этот ключ необходимо отправить админу банка, для настройки терминала

Данные, которые даёт банк: 
Terminal ID: xxxxxxxx - 8 цифр
Card Acceptor ID: xxxxxxxxxxxxxxx - 15 цифр

Банк затребует URL для постирования ответов:
Указать
https://site.md/assets/components/minishop2/payment/victoriabankmd.php