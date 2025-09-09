# WooCommerce UPN Nalog

Vzdrževana različica vtičnika WooCommerce UPN, ki prikazuje podatke o plačilu in UPN položnice z QR kodami na koncu naročil, v e-poštnih sporočilih za potrditev naročila in v pregledih naročil.

![alt](pic1.png)

## Namestitev

**Prenesite [zadnjo različico](https://github.com/tyfricko/woo-upn-nalog/releases/latest) »

Ali namestite prek Composerja:

```bash
composer require tyfricko/woo-upn-nalog
```

Za delovanje potrebuje nastavljen BACS (Direct Bank Transfer plačilni modul).

Pozor! IBAN mora biti pravilen drugače se obrazec ne prikaže.

Za prilagajanje se lahko uporabi naslednje filtre:

```php
apply_filters('upn_code', function(){return "OTHR";});
apply_filters('upn_reference', function(){return "SI00 %s";});
apply_filters('upn_purpose', function(){return 'Plačilo naročila %s';});
```

Pri page builder Elementor se UPN za neprijavljene uporabnike ne prikazuje. Če želite, da se UPN nalog prikazuje tudi za neprijavljene uporabnike, v functions.php dodatke sledeče.

```php
add_action( 'woocommerce_thankyou', 'adding_customers_details_to_thankyou', 10, 1 );
function adding_customers_details_to_thankyou( $order_id ) {
    // Only for non logged in users
    if ( ! $order_id || is_user_logged_in() ) return;

    $order = wc_get_order($order_id); // Get an instance of the WC_Order object

    wc_get_template( 'order/order-details-customer.php', array('order' => $order ));
}
```

## Dnevnik sprememb

### 1.3.0
- Posodobljeno na `media24si/upn-generator` v3.0.1 z izvorno integracijo `endroid/qr-code`
- Migrirano iz zastarelega `werneckbh/qr-code` na sodoben `endroid/qr-code` za generiranje QR kode
- Izboljšana kakovost QR kode in obravnava napak
- Povečana združljivost s PHP 8.1+ in najnovejšimi različicami WooCommerce
- Ohranjen format UPNQR za slovenske bančne standarde

### 1.2.2
- Popravljena opozorila o zastarelosti PHP 8.1+ za implicitne pretvorbe float-to-int v generiranju QR kode
- Posodobljen kodirnik QR za uporabo celoštevilčnega deljenja za indekse polj

## O projektu

To je vzdrževana različica izvirnega vtičnika WooCommerce UPN podjetja WooCart. Zagotavlja združljivost z modernimi različicami PHP in WooCommerce, hkrati pa ohranja format UPNQR za slovenske bančne standarde.

## Avtor

[Matej Zlatič](https://matejzlatic.com) - Stranski projekt za izboljšave WooCommerce UPN.

Izvirni razvijalec: [WooCart](https://woocart.com/) se specializira za gostovanje WooCommerce. [Kontakt](https://woocart.com/contact).
