# pricer

[![Build Status](https://travis-ci.org/spacefoot/pricer.svg?branch=master)](https://travis-ci.org/spacefoot/pricer)

The pricer is a tool used to compute a discounted price according to different parameters.

## Install library

This a composer library, not registered in packagist, first add the repository in your composer.json:


```json
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/spacefoot/pricer.git"
    }
],

```

Then install:

```bash
composer install spacefoot/pricer --save
```

## General purpose

This software library is designed to compute a better price on products using defined markups and competitors price on the same product.

Example with default options:

```php
use Pricer\Pricer;

$pricer = new Pricer();
$pricer->setTargetMarkup(30);
$pricer->setAlignMarkup(15);

$basePrice = 14.80;
$purchasePrice = 10.00;

$pricer->getWinningPrice($basePrice, $purchasePrice);
// return a WinningPrice object with 14.29

// with a competitor
$competitorPrice = 11.90;
$pricer->getWinningPrice($basePrice, $purchasePrice, $competitorPrice);
// return a WinningPrice object with 11.89

```

## Output price

The pricer output a `WinningPrice` object with properties:

### value

Pricer output to use as a discounted price

### type

Contain one of the following possible type

| Constant                        | type value                                |
|---------------------------------|-------------------------------------------|
| WinningPrice::BASE              | Unmodified base price                     |
| WinningPrice::COMPETITOR        | Aligned to competitor                     |
| WinningPrice::COMPETITOR_ALWAYS | Aligned to competitor above target markup |
| WinningPrice::MIN               | Limited by align markup                   |
| WinningPrice::TARGET            | Limited by target markup                  |
| WinningPrice::MIN_RATED         | Max price drop from base price            |


## price calculation

### Markups for price computed from purchase price

__Align markup__: This is the markup on selling price in percentage used to compute the lower limit of a price aligned to competitor price.

The default value is null.

__Target markup__: This is the markup on selling price in percentage used to compute the price when there is no competitor.

The default value is null. The target markup is mandatory if the purchase price is given as third argument in the `getWinningPrice` method.


```php
$pricer->setAlignMarkup(20);
$pricer->setTargetMarkup(10);
```

| Purchase price | Competitive price | Pricer output |
|---------------:|------------------:|--------------:|
|          10.00 |             11.00 |         11.11 |
|          10.00 |             12.00 |         11.99 |
|          10.00 |             13.00 |         12.50 |
|          10.00 |              NULL |         12.50 |


### Drop rate when purchase price is unknown

If purcharse price is unknown, the target price and align price can not be computed. The pricer compute a discounted price from a base price, when a competitor is lower than the base price, the pricer compute a lower price aligned to competitor is the limit defined by the drop rate.

The default value is 10%, the `setDropRate` can be used to change the drop rate.

```php
$pricer->setDropRate(10);
```

| Base price | Competitive price | Pricer output |
|-----------:|------------------:|--------------:|
|      10.00 |             10.83 |         10.82 |
|      10.00 |              9.00 |          9.00 |
|      10.00 |              8.91 |          9.00 |

Drop rate is used only when a competitor exists.


### Alignment to a competitor

When a competitive price is within allowed price update range, the default behaviour is the output a price lower than the competitive price by 0.01 this can be modified to increase the price drop:

```php
$pricer->setCompetitorGap(0.03);
```

### Competitor policies

#### If there is a competitor

When there is a competitor, the target markup apply if the target price is lower than the competitor. The competitor price will be used only if bellow target price but alignment will be limited by the align markup.


Disable competitor aligment:

```php
$pricer->setCompetitorPolicy(Pricer::NO_ALIGN);
```
Possibles values for this method are:

`Pricer::ALIGN`: Drop the price if the competitor price is lower than base price.

If the competitor alignment is enabled, the pricer will apply a price drop to align to competitor in two cases:

* there is no purchase price, and the price drop rate is greater than zero.
* there is a purchase price, if the align rate is not set, the pricer throw an exception.

`Pricer::ALIGN_ALWAYS`: Same behavior but raise target price if below competitor price

`Pricer::NO_ALIGN`: Do not drop price if the competitor have a lower price, competitor value will be ignored but if the target markup is set the price will be lowered accordingly.


#### If there is no competitor

Disable price decrease to target price:

```php
$pricer->setNoCompetitorPolicy(Pricer::BASE_PRICE);
```

Possibles values for this method are:

* `Pricer::BASE_PRICE`: Output the base price if there is no competitor.
* `Pricer::TARGET_BELOW_BASE_PRICE`: Use the target markup to drop base price up to the target markup. The pricer will never output a price higher than the current price to ensure that special discounts are retained as is.
* `Pricer::TARGET_PRICE`: Use the target markup to change the base price according to the target markup.

Default value is `Pricer::TARGET_BELOW_BASE_PRICE`

for `Pricer::TARGET_BELOW_BASE_PRICE` and `Pricer::TARGET_PRICE`, the purchase price will be used, if the pucharse price is not set, the pricer fallback on `Pricer::BASE_PRICE`.

If target markup is not defined with `setTargetMarkup`, the pricer will output the purchase price because default target markup is 0.


## Shipping price/Shipping cost


### Set the shipping cost base (real cost of your shipping)

The shipping cost is the real cost of the shipping.
The computed shipping price can be different than the shipping cost.

```php
$pricer->setShippingCost(5.99);
```
the different between shipping cost and shipping price is accounted in the pricer output.


### Set a shipping scale

A shipping scale is an array with a higher limit and a shipping amount. The shipping scale contain the possibles shipping price that a customer can pay.

For the last shipping scale entry, the higher limit is set to NULL. The shipping cost is required if the shipping scale contain rows.

```php
$pricer->setShippingCost(5.99);
$pricer->setShippingScale(
    [
        [20,    5.99],
        [70,    2.99],
        [null,  0]
    ]
);
```

In this example, if pricer output is greater than 20 but lower than 70, the shipping price will be 2.99

## Fees

It is possible to add a percentage charge that will be taken into account in the price calculation.

The same fee rate is used to compute fees on price and fees on shipping cost, on a default pricer instance, there is no fees.

Apply fees on pricer output with a fee rate on percentage:

```php
$pricer->setFeeRate(10);
```


To disable fees on shipping cost, the setShippingFee method can be used:

```php
$pricer->setFeeOnShipping(false);
```


## Raise base price when below a minimum markup (NEW)

Set those options to raise the base price when it's below the minimum markup.

```php
$pricer->setMinMarkup(10);
$pricer->setRaiseBasePriceIfBelowMinMarkup(true);
```

_Note: the min markup must be set to enable this behavior. Else an exception will be throwed._