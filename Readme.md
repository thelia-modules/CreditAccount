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

## How to use it

In your back-office, on each customer edition page you can add a new credit account. 

## Integration

In back-office, the module places itself in the `account.bottom` hook, which is in the customer details page.

Two loops are available with this module. One for the credit account history and the other one for the current credit account.

A route exists allowing customer to consume his credit account, within the limits of order total, excluded shipping. You just have to call the ```/creditAccount/use``` and the controller will find the credit account of the current customer and put the available amount in the discount amount.

To cancel the use of credit account, use the ```/creditAccount/cancel``` route.

Invoking these routes will redirect the customer to the ```order.invoice``` route.

The module creates a hook, `order-invoice.before-discount`, where the code which allows your customer to use their credit is inserted.
You have to put the hook in the `order-invoice.html` template file, just before the "Discount" block for example. 

### Code to insert in the `order-invoice.html` file in your template:

```smarty
{hook name='order-invoice.before-discount'}
```

### Hooks

The module uses the front office `account.bottom` hook to display account history in the customer "My Account" page.

It also uses the `following back-office hooks 
- `account.bottom` to display customer account history in customer details.
- `order-edit.after-order-product-list` to display in order detail the entries added to a credit account by the order.

It creates a `order-invoice.before-discount` which has to be inserted in the `order-invoice.html` template file.

## Loops

### credit_account_history loop

List the credit account history for a specified credit account.

#### Input arguments

* credit_account : credit account id, it's a mandatory parameter.
* order : an order ID, to get only entries related to this order. 

#### Output variables

* `$CREDIT_AMOUNT` the entry amount, either positive or negative, in the default currency
* `$ORDER_ID` the related order ID, where an amount has been gained or used. 0 si this entry is a manual operation
* `$HAS_ORDER_ID` false for a manual operation, true if related to an order
* `$WHO_DID_IT` name of the administrator who did a manual operation
* `$CREATE_DATE`
* `$UPDATE_DATE`

#### Example

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

### credit_account loop

retrieve the current credit account for a specified customer

#### Input argument

* customer : customer id. If not specified, all credit accounts are returned.

#### Output variables

* $ID : credit account id
* $CREDIT_AMOUNT current balance in the default currency
* $CREATE_DATE
* $UPDATE_DATE

#### Example usage

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

### credit_account_usage loop

Returns the credit amount used on the current order for the current customer, or nothing if no credit amount is currently in use.

#### Input argument

None.

#### Output variables

- `$AMOUNT_USED` `the amount from credit account used on the current order in the default currency.

#### Example usage

```smarty
{loop type="credit_account_usage" name="credit-used"}
    {intl l="You're using %amount from your credit account." amount={format_money number=$AMOUNT_USED symbol=$currencySymbol}}
    <a class="btn btn-xs btn-warning" href="{url path="/creditAccount/cancel"}">{intl l='Cancel'}</a>
{/loop}
```

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
