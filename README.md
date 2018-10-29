## mspVictoriabankmd
*(Компонент предоставляется "как есть" и автор не несёт какой-либо ответственности за "у меня не работает")*

Card payment method by [Victoriabank.md](http://www.victoriabank.md/) (Moldova) for miniShop2

* Создать приватный ключ с помощью OpenSSL

```openssl genrsa -f4 -out key.pem 2048```

* Этот ключ, после установки модуля, поместить в папку
 ```components/minishop2/custom/payment/lib/victoriabankmd/```

**Там же должен уже лежить публичный ключ от банка victoria_pub.pem**

* Создать публичный ключ

```openssl rsa -pubout -in key.pem -out pubkey.pem```

Этот ключ необходимо отправить админу техотдела банка, для настройки терминала

Данные, которые даёт банк: 

*Terminal ID: xxxxxxxx - 8 цифр*

*Card Acceptor ID: xxxxxxxxxxxxxxx - 15 цифр*

**Банк затребует URL для постирования ответов:**

Указать

```https://site.md/assets/components/minishop2/payment/victoriabankmd.php```

------------


После проведения тестовых платежей необходимо сообщить в техотедл банка данные тестов.
В предоставляемой банком документации есть необходимые данные.
В идеале 3 тестовых данных
* Оплата - подтверждение
* Оплата - возврат средств
* Оплата - подтверждение - возврат средств


**Чтобы провести тесты, необходимо (29.10.2018):**
1. открыть файл assets/components/minishop2/payment/victoriabankmd.php 
2. найти строки где перечислены тесты (по умолчанию они все false)
3. Установить необходимй **true** а остальные в **false**
4. провести тестовые платежи
5. на почту придут поочередно письма от egateway, собрать данные из строки RRN=******* и передать их в тенический отдел
6. RRN в рамках одной операции идентичны

-----------------------

Письмо должно содержать примерно такие строки:


1. 
Authorization request 
Sales completion request

TRTYPE = 0
RRN=********** 
TRTYPE = 21
RRN=********** 

2. 
Authorization request
Reversal request

TRTYPE = 0
RRN==********** 

TRTYPE = 24
RRN==********** 


3. 
Authorization request
Sales completion request
Reversal request

TRTYPE = 0
RRN==********** 

TRTYPE = 21
RRN==**********

TRTYPE = 24
RRN==**********

-----------------------------

Возможно включение проведения тестов будут перенесено в системные настройки, но пока так.



### Системные настройки

Системные настройки, появляются после оплаты в разделе **Системные настройки - minishop2 - Платежи**

                    
Название |  Описание | Ключ  | Значение |  
------------- | ------------- | ------------ | ------------ |
Валюта платежа | Трехбуквеннй код валюты (MDL, USD, EUR)  | ms2_payment_vcbmd_currency | MDL 
Адрес для запросов | Адрес для отправки запросов на удалённый сервис Victoriabank | ms2_payment_vcbmd_url| https://egateway.victoriabank.md/cgi-bin/cgi_link
ID терминала | Выдается банком |ms2_payment_vcbmd_terminal_id| -
MERCHANT| Выдается банком |ms2_payment_vcbmd_merchant_id| -
Имя организации продавца||ms2_payment_vcbmd_merch_name| - 
Язык формы оплаты| На каком языке будет форма ввода данных для оплаты (на стороне банка). Возможны варианты ru/ro/en| ms2_payment_vcbmd_language |ru
Страница успешной оплаты - id| Пользователь будет отправлен на эту страницу после завершения оплаты. Лучше указать id страницы корзины| ms2_payment_vcbmd_success_id | 1
Разница времени GMT | Укажите разницу времени между сервером и вашей страной | ms2_payment_vcbmd_merch_gmt | 2