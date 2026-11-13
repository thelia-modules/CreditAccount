# Credit account

Store credit for the customers of a Thelia 3 shop. Version 4.0 requires Thelia 3.3.

## What it does

- Every customer has a credit account. Its balance is the sum of a journal of movements: a credit and a debit are movements, never a value written apart.
- A customer earns credit on orders (loyalty), by a percentage of the order or from a grid of tiers. The earning is taken back when the order is canceled or refunded.
- A credit can expire after a delay, with a warning email before it does.
- An administrator can credit or debit a customer by hand, with a reason that is always recorded in the journal and in the administration log.
- The customer can read the balance and the journal through the API, and the journal is part of the personal data export and of the anonymization.

Spending the credit when paying an order is **coming**: this version does not apply the credit at the payment yet.

## Installation

```
cd local/modules
git clone https://github.com/thelia-modules/CreditAccount
```

Then activate the module in the back-office, or with `php Thelia module:refresh && php Thelia module:activate CreditAccount`.

## Configuration

Open the module in the back-office (Modules, then Configure). Every setting is on that page:

- **Expiration**: on or off, delay in months, and how many days before the expiry the customer is warned (0 for no warning). A credit expires from the day it is written; turning expiration off does not change the date of the credits already written.
- **Loyalty**: the order status from which an order earns, then the mode:
  - none: nothing is earned;
  - percentage: a percentage of the eligible amount of the order;
  - grid: an order whose eligible amount is from the minimum of a tier (included) up to its maximum (excluded) earns the amount of that tier. The tiers must not overlap and only the last one may have no maximum. A single tier with no maximum is a valid grid of one line.
- **Exclusions**: categories and products (by reference) whose lines do not count in the eligible amount of an order.

The module is in the `module.configuration` hook for its configuration page, in `customer.edit` for the customer sheet (balance, paginated journal, manual movement) and in `order-edit.after-order-product-list` for the credit movements of an order. Its script is published with `module_asset()`.

## Commands

| Command | What it does |
|---|---|
| `php Thelia credit-account:expire [--limit=200]` | Warns the customers whose credit is about to expire and expires the credit that reached its date unspent. Does nothing while expiration is off. **Schedule it every day** (cron): nothing else triggers the expiration. |
| `php Thelia credit-account:recalculate [--fix]` | Compares the balance each account keeps in cache with the sum of its movements, the one that counts. Exits with a failure when they differ; `--fix` rewrites the cache. |

Example of a crontab line:

```
15 3 * * * cd /path/to/shop && php Thelia credit-account:expire
```

## Rights

A manual movement (back-office or API) needs a dedicated administrator right, `module.creditaccount.manual-credit`, granted apart from the customer rights. Reading the balance or the journal of a customer needs the `admin.customer` right. Changing the configuration needs the update right on the module.

## API

Amounts are decimal strings with two decimals (`"12.50"`); a credit is positive, a debit negative.

| Method and path | Who | What |
|---|---|---|
| `GET /api/front/account/credit/balance` | the signed-in customer | their balance |
| `GET /api/front/account/credit/movements` | the signed-in customer | their journal, newest first, paginated |
| `GET /api/admin/credit_account/customers/{customerId}/balance` | administrator, customer right | the balance of that customer |
| `GET /api/admin/credit_account/customers/{customerId}/movements` | administrator, customer right | their journal, with who wrote each movement |
| `POST /api/admin/credit_account/customers/{customerId}/adjustments` | administrator, manual credit right | writes a movement: `{"amount": "-3", "reason": "Goodwill"}` |

The reason is required. A debit larger than the balance is refused with a 422: the balance never goes negative.

## Events

`creditAccount.addAccount` is still accepted, for the modules written for 3.x (sponsorship, gift card). Dispatch a `CreditAccount\Event\CreditAccountEvent` with the customer and the amount (negative to debit); it is written in the journal as a manual movement, with the reason and the name set on the event if any.

```php
$event = new \CreditAccount\Event\CreditAccountEvent($customer, 10);
$dispatcher->dispatch($event, \CreditAccount\CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);
```

## Updating from 3.x

Activate the new version: the update runs by itself.

- the history of 3.x is copied into the journal, once; when the balance a 3.x account showed differs from the sum of its history, a carry movement closes the gap, so each customer keeps the amount they saw;
- the expiry date of a 3.x account is moved onto its credits;
- an account is now unique per customer;
- the 3.x tables are read and kept, never written.

Then check the result with `php Thelia credit-account:recalculate`, and schedule `credit-account:expire`.

The Smarty loops, the `/creditAccount/use` and `/creditAccount/cancel` routes and the Open API controller of 3.x are gone, as is the Smarty back-office.

---

# Compte crédit

Crédit client pour une boutique Thelia 3. La version 4.0 demande Thelia 3.3.

## Ce que fait le module

- Chaque client a un compte crédit. Son solde est la somme d'un journal de mouvements : un crédit et un débit sont des mouvements, jamais une valeur écrite à part.
- Un client gagne du crédit sur ses commandes (fidélité), par un pourcentage de la commande ou selon une grille de tranches. Le gain est repris si la commande est annulée ou remboursée.
- Un crédit peut expirer après un délai, avec un e-mail d'avertissement avant.
- Un administrateur peut créditer ou débiter un client à la main, avec un motif toujours consigné dans le journal et dans le journal d'administration.
- Le client lit son solde et son journal par l'API ; le journal fait partie de l'export des données personnelles et de l'anonymisation.

La dépense du crédit au paiement d'une commande est **à venir** : cette version n'applique pas encore le crédit au paiement.

## Configuration

Page de configuration du module dans le back-office (Modules, puis Configurer) : expiration (activation, délai en mois, jours d'avertissement), fidélité (statut déclencheur, mode aucun, pourcentage ou grille de tranches min/max/montant, une tranche unique sans maximum étant une grille d'une ligne) et exclusions par catégorie et par référence de produit.

## Commandes

- `php Thelia credit-account:expire` : avertit et fait expirer les crédits. **À planifier chaque jour (cron)**.
- `php Thelia credit-account:recalculate [--fix]` : compare le solde en cache à la somme des mouvements ; `--fix` le réécrit.

## Droits et API

Un mouvement manuel (back-office ou API) demande le droit dédié `module.creditaccount.manual-credit`, accordé à part des droits client. Les points d'accès de l'API sont listés dans la partie anglaise.

## Mise à jour depuis la 3.x

L'activation de la nouvelle version lance la migration automatique : l'historique 3.x est copié dans le journal, un mouvement de report comble l'écart éventuel avec le solde affiché, la date d'expiration passe sur les crédits, et les tables 3.x sont conservées. L'ancien événement `creditAccount.addAccount` reste accepté. Les boucles Smarty, les routes `/creditAccount/use` et `/creditAccount/cancel` et le back-office Smarty ont disparu.
