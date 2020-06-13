# CloudPayments module for WordPress - WooCommerce

Модуль позволит добавить на ваш сайт оплату банковскими картами через платежный сервис [CloudPayments](https://cloudpayments.ru/Docs/Connect).
Для корректной работы модуля необходима регистрация в сервисе.
Порядок регистрации описан в [документации CloudPayments](https://cloudpayments.ru/Docs/Connect).

## Возможности:

* Одностадийная схема оплаты;
* Двухстадийная схема;
* Информирование СMS о статусе платежа;
* Выбор языка виджета;
* Выбор дизайна виджета;
* Создание подписки (совместно с мудулем WooCommerce Subscriptions);
* Отмену подписки (совместно с мудулем WooCommerce Subscriptions);
* Поддержка онлайн-касс (ФЗ-54);
* Отправка чеков по email;
* Отправка чеков по SMS;
* Теги способа и предмета расчета;
* Отдельный параметр НДС для доставки.

## Совместимость:

WordPress 4.9.7 и выше;
WooCommerce 3.4.4 и выше;
WooCommerce Subscriptions 2.5.3 и выще.

## Установка

Скопируйте папку `woocommerce-cloudpayments` в директорию `wp-content/plugins/` на вашем сервере.

Зайдите в "Управление сайтом" -> "Плагины". Активируйте плагин "WooCommerce CloudPayments Gateway".

В управлении сайтом зайдите в "WooCommerce" -> "Настройки" -> "Оплата" -> "CloudPayments". Отметьте галочкой  "Enable CloudPayments".

Настройте необходимые поля:
![CPsettings](pics/settings.png)

* **Включить/Выключить** - Включение/Отключение платежной системы;
* **Включить DMS** - Включение двухстадийной схемы оплата платежа (холдирование);
* **Статус для оплаченного заказа** - **Обработка** (Если не предусматривается другой функционал);
* **Статус для отмененного заказа** - **Отменен** (Если не предусматривается другой функционал);
* **Статус авторизованоого платежа DMS** - **На удержании** (Или **Платеж авторизован**, если предусматривается другой функционал);
* **Наименование** - Заголовок, который видит пользователь в процессе оформления заказа;
* **Описание** - Описание метода оплаты;
* **Public_id** - Public id сайта из личного кабинета CloudPayments;
* **Password for API** - API Secret из личного кабинета CloudPayments;
* **Валюта магазина** - Российский рубль (Если не предусматривается использовать другие валюты);
* **Дизайн виджета** - Выбор дизайна виджета из 3 возожных (classic, modern, mini);
* **Язык виджета** - Русский МСК (Если не предусматривается использовать другие языки);
* **Включить/Выключить** - Включение формирования заказа в подписках (_версия Subscriptions_).

Использовать функцуионал онлайн касс:
* **Включить/Выключить** - Включение/отключение формирования онлайн-чека при оплате;
* **ИНН** - ИНН вашей организации или ИП, на который зарегистрирована касса;
* **Ставка НДС** - Укажите ставку НДС товаров;
* **Ставка НДС для доставки** - Укажите ставку НДС службы доставки;
* **Система налогообложения организации** - Тип системы налогообложения;
* **Способ расчета** - признак способа расчета;
* **Предмет расчета** - признак предмета расчета;
* **Действие со штрих-кодом** - отправление артикула товара в чек как шрих-код;
* **Статус доставки** - **Выполнен** (Или **Доставлен**, если предусматривается другой функционал)
_Отдельный статус доставки необходим при формировании двух чеков: один чек - при поступлении денег от покупателя, второй при отгрузке товара. Отправка второго чека возможна при следующих способах расчета: Предоплата, Предоплата 100%, Аванс._


Нажмите "Сохранить изменения".

В личном кабинете CloudPayments зайдите в настройки сайта, пропишите в настройках уведомления, как описано на странице настройки модуля на указанный адрес:
![webHooks](pics/Webhook.png)

Вы готовы принимать платежи с банковских карт с помощью CloudPayments

# Платежи по подписке

Если вы используете модуль Subscribtions для WooCommerce, то используйте содержимое каталога "For Subscriptions".

## Для разработчиков

### Формирование данных заказа на странице оплаты

Фильтр `woocommerce_cpgwwc_payment_page_delivery_item` позволяет изменить данные доставки перед перед оплатой.

```php
$shipping_data = apply_filters( 'woocommerce_cpgwwc_payment_page_delivery_item', array(
  'label'    => 'Доставка',
  'price'    => number_format( (float) $order->get_total_shipping() + abs( (float) $order->get_shipping_tax() ), 2, '.', '' ),
  'quantity' => '1.00',
  'amount'   => number_format( (float) $order->get_total_shipping() + abs( (float) $order->get_shipping_tax() ), 2, '.', '' ),
  'vat'      => ( $this->delivery_taxtype == 'null' ) ? null : $this->delivery_taxtype,
  'method'   => (int) $this->kassa_method,
  'object'   => 4,
  'ean'      => null,
), $order, $this );
```

Фильтр `woocommerce_cpgwwc_payment_page_product_item` позволяет изменить данные товара перед оплатой заказа.

```php
$items_array[] = apply_filters( 'woocommerce_cpgwwc_payment_page_product_item', array(
  'label'    => $item_data['name'],
  'price'    => number_format( (float) $product->get_price(), 2, '.', '' ),
  'quantity' => number_format( (float) $item_data['quantity'], 2, '.', '' ),
  'amount'   => number_format( (float) $item_data['total'] + abs( (float) $item_data['total_tax'] ), 2, '.', '' ),
  'vat'      => ( $this->kassa_taxtype == "null" ) ? null : $this->kassa_taxtype,
  'method'   => (int) $this->kassa_method,
  'object'   => (int) $this->kassa_object,
  'ean'      => ( $this->kassa_skubarcode == 'yes' ) ? ( (strlen( $product->get_sku() ) < 1 ) ? null : $product->get_sku() ) : null,
), $product, $item_id, $item_data, $this );
```

### Формирование данных заказа на при смене статуса заказа

Фильтр `woocommerce_cpgwwc_before_send_receipt_product_item` позволяет изменить данные товара перед добавлением его в чек.

```php
$items[] = apply_filters( 'woocommerce_cpgwwc_before_send_receipt_product_item', array(
  'label'    => $product->get_name(),
  'price'    => number_format($product->get_price(),2,".",''),
  'quantity' => $item_data->get_quantity(),
  'amount'   => number_format(floatval($item_data->get_total()),2,".",''),
  'vat'      => $this->kassa_taxtype,
  'method'   => $method,
  'object'   => (int)$this->kassa_object,
), $product, $item_id, $item_data, $method, $this );
```

Фильтр `woocommerce_cpgwwc_before_send_receipt_delivery_item` позволяет изменить данные метода доставки перед добавлением его в чек.

```php
$items[] = apply_filters( 'woocommerce_cpgwwc_before_send_receipt_delivery_item', array(
  'label'    => "Доставка",
  'price'    => $order->get_total_shipping(),
  'quantity' => 1,
  'amount'   => $order->get_total_shipping(),
  'vat'      => $this->delivery_taxtype,
  'method'   => $method,
  'object'   => 4,
), $order, $this );
```

Фильтр `woocommerce_cpgwwc_before_send_receipt_data` позволяет изменить данные всего объекта чека перед его отправкой в CloudPayments.

```php
$aData = apply_filters( 'woocommerce_cpgwwc_before_send_receipt_data', array(
  'Inn'             => $this->inn,
  'InvoiceId'       => $order->get_id(), //номер заказа, необязательный
  'AccountId'       => $order->get_user_id(),
  'Type'            => $type,
  'CustomerReceipt' => $data['cloudPayments']['customerReceipt'],
), $order, $this );
```


== Changelog ==
= 2.0.2 For Subscriptions =
* Добавлен возможность отменить подписку.

= 2.0 =
* Добавлены теги способов и предметов расчета;
* Устранены некоторые ошибки;
* Публикация плагина на маркетплейс.

= 1.0 =
* Публикация плагина на [GitHub](https://github.com/cloudpayments/CMS-WordPress-WooCommerce-CK).

