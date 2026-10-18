## English Instructions

# Credit account

This module is a base for creating module like sponsorship or assets management.

##Installation

```
$ cd local/modules
$ git clone https://github.com/thelia-modules/CreditAccount
```

You could also download the zip from github.

After that, you just have to activate the module in your back-office.

## Thelia 3 — Flexy front-office components

On the Thelia 3 (twig branch) front-office, the Flexy theme has **no front hook system**: components must be mounted explicitly in the theme templates. This module ships two ready-to-use components (namespace `@CreditAccountModule`).

### `CreditAccount:Balance` (TwigComponent)

Displays the current customer's credit balance. Mount it on the customer account page:

```twig
{{ component('CreditAccount:Balance') }}
```

### `CreditAccount:CreditForm` (LiveComponent)

Lets a logged-in customer apply or remove their credit as a discount during checkout. It applies the discount through the cart (`Thelia\Domain\Cart\CartFacade`), recomputes the postage, then emits the `syncSummary` event so the checkout summary re-renders. Mount it in the checkout summary, next to the promo-code form:

```twig
{{ component('CreditAccount:CreditForm') }}
```

The component renders nothing when the customer has no available credit, so it is safe to mount unconditionally. The `Flexy:Checkout:Summary` component already listens to `syncSummary`, so the applied discount and the updated total appear automatically.

> The `/creditAccount/*` routes are kept for backward compatibility, but the Flexy front-office uses the components above.

## How to use it

In your back-office, on each customer edition page you can add a new credit account. 

## Integration

In back-office, the module places itself in the `customer.edit` hook, which is in the customer details page.

A route exists allowing customer to consume his credit account, within the limits of order total, excluded shipping. You just have to call the ```/creditAccount/use``` and the controller will find the credit account of the current customer and put the available amount in the discount amount.

To cancel the use of credit account, use the ```/creditAccount/cancel``` route.

Invoking these routes will redirect the customer to the ```order.invoice``` route.

## Expiration
Since version 1.3.0 of this module you can enable expiration of credit.   
Just go in the CreditAccount configuration end enable it (it's disabled by default) then you can choose the expiration delay (in months).   
The date of expiration is computed with the last date of creditation + your expiration delay.  
Expiration is checked each time the customer add an item in cart but if you want you can set this a command as cron to force expiration check `php Thelia creditaccount:expiration:check`.


### Hooks

The module uses the following back-office hooks:
- `customer.edit` to display the customer credit account in customer details.
- `order-edit.after-order-product-list` to display in order detail the entries added to a credit account by the order.

## Listeners

This module is a base for creating module like sponsorship or assets management. So some listeners are preconfigured for using it in other modules.

### creditAccount.addAccount

This listener is used for adding an amount to a customer's credit account. You have to dispatch a ```CreditAccount\Event\CreditAccountEvent``` class

#### Example

```
//retrieve the dispatcher for using it after. I put it in $dispatcher

// retrieve a customer. For the example I retrieve a random customer, obviously not do that in your application
$customer = \Thelia\Model\CustomerQuery::create()->findOne();

//the amount I want to add to the current customer's credit account
$amount = 10;

// Create the event to dispatch
$event = new \CreditAccount\Event\CreditAccountEvent($customer, $amount)

//dispatch the event
$dispatcher->dispatch(\CreditAccount\CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT, $event);

```

---

## Instructions en Français

# Crédit client

Ce module permet d'ajouter un crédit à chacun de vos clients. Il est aussi nécessaire pour des modules du type Fidélisation ou Gestion des avoirs.

##Installation

```
$ cd local/modules
$ git clone https://github.com/thelia-modules/CreditAccount
```

Vous pouvez aussi téléchargez le zip depuis Github.

Ensuite vous n'avez plus qu'à activer le module dans votre back-office. 

## Utilisation

Dans votre back-office, vous pouvez ajouter pour chaque client un crédit fidélité. Pour cela, rendez-vous sur la page d'édition du compte client. 

## Intégration

Trois boucles sont disponibles pour ce module: une pour l'historique du compte crédit, une pour obtenir le solde du compte, et une pour connaitre l'utilisation du compte sur la commande en cours. 

Pour permettre au client d'utiliser son crédit, il suffit d'appeler la route ```/creditAccount/use```. Le contrôleur trouvera lui-même le crédit du client en question, et l'ajoutera dans le montant de la réduction. Pour annuler l'utilisation du crédit, utiliser la route ```/creditAccount/cancel```

Ce module crée un nouveau point d'accroche, `order-invoice.before-discount`, où le code qui permet à vos clients d'utiliser leur crédit fidélité sera inséré.
Placez le code de ce point d'accorche dans le fichier `order-invoice.html` de votre template, juste avant le bloc Remise par exemple.

### Code to insert in the `order-invoice.html` file in your template:

### Code du point d'accorche à insérer dans le fichier `order-invoice.html` de votre template:

```smarty
{hook name='order-invoice.before-discount'}
```

## Boucles

### Boucle credit_account_history

Liste l'historique des operations sur un compte donné

### Paramètres

* credit_account : credit account id, identifiant du compte, paramètre obligatoire

### Résultats

* $CREDIT_AMOUNT
* $ORDER_ID l'ID de la commande associé, ou 0 si l'operation est manuelle
* $HAS_ORDER_ID false pour une opération manuelle, true sinon.
* $WHO_DID_IT nom de l'administrateur pour une opération manuelle.
* $CREATE_DATE
* $UPDATE_DATE

### Exemple d'utilisation

```smarty
{loop type="order" name="order-account-order" id=$order_id customer="*"}
    {loop type="credit_account" name="credit_account" customer=$CUSTOMER}
        {loop type="credit_account_history" name="credit_account_history" credit_account=$ID order=$order_id}
            {if $CREDIT_AMOUNT < 0}
                {$class='class="text-danger"'}
            {else}
                {$class=''}
            {/if}

            <li>{intl l="New entry in customer credit account : <span %class>%amount</span>" class=$class amount={format_money number=$CREDIT_AMOUNT symbol=$currencySymbol}}</li>
        {/loop}
    {/loop}
    {elseloop rel="order-account-order"}
        <li>{intl l="No new entry in customer credit account for this order."}</li>
    {/elseloop}
{/loop}
```

### Credit account loop

Récupère le crédit en cours pour un client donné

#### Paramètres

* customer : ID client. Si absente, les informations sur tous les comptes sont retournés.

#### Résultats

* $ID : credit account id, identifiant du compte
* $CUSTIMER_ID : identifiant du client
* $CREDIT_AMOUNT : solde du compte
* $CREATE_DATE
* $UPDATE_DATE

#### Exemple d'utilisation

```smarty
{loop type="credit_account" name="credit_account" customer={customer attr="id"}}
    {if $CREDIT_AMOUNT > 0}
        <tr>
            <th colspan="2" class="discount">
                <a class="btn btn-success" href="{url path="/creditAccount/use"}">
                    {intl l="Use my credit account (%amount available)" amount={format_money number=$CREDIT_AMOUNT symbol=$currencySymbol}}
                </a>
            </th>
        </tr>
    {/if}
{/loop}
```

### Boucle credit_account_usage

Cette boucle retourne le crédit utilisé sur la commande courant par le client courant, ou rien si le compte n'est pas utilisé sur la commande.

#### Paramètres

Aucun.

#### Résultats

- `$AMOUNT_USED` `the amount from credit account used on the current order in the default currency.

#### Exemple d'utilisation

```smarty
{loop type="credit_account_usage" name="credit-used"}
    {intl l="You're using %amount from your credit account." amount={format_money number=$AMOUNT_USED symbol=$currencySymbol}}
    <a class="btn btn-xs btn-warning" href="{url path="/creditAccount/cancel"}">{intl l='Cancel'}</a>
{/loop}
```


## Listeners

Ce module est une base pour créer des modules comme Fidélisation, ou Gestion des avoirs. 
Des "listeners" sont préconfigurés pour être utilisés dans d'autres modules. 

### creditAccount.addAccount

Ce listener est utilisé pour ajouter un montant dans un compte crédit. Vous devez diffuser un évènement instance de ```CreditAccount\Event\CreditAccountEvent```

#### Exemple d'utilisation

```
//retrieve the dispatcher for using it after. I put it in $dispatcher

// retrieve a customer. For the example I retrieve a random customer, obviously not do that in your application
$customer = \Thelia\Model\CustomerQuery::create()->findOne();

//the amount I want to add to the current customer's credit account
$amount = 10;

// Create the event to dispatch
$event = new \CreditAccount\Event\CreditAccountEvent($customer, $amount)

//dispatch the event
$dispatcher->dispatch(\CreditAccount\CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT, $event);

```
