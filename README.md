# pricer

The pricer is a tool used to compute a discounted price according to different parameters.

## General purpose

This software library is designed to compute a better price on products using defined markups and competitors price on the same product. The pricer will never output a price higher than the current price to ensure that special discounts are retained as is.

Example with default options:

```php
use Pricer\Pricer;

$pricer = new Pricer();
$pricer->setTargetMarkup(30);
$pricer->setAlignMarkup(15);

$basePrice = 14.80;
$purchasePrice = 10.00;

$pricer->getProductPrice($basePrice, $purchasePrice);
// return a ProductPrice object with 14.29

// with a competitor
$competitor = new Competitor();
$competitor->sellingPrice = 10.90;
$competitor->name = 'Seller name';
$pricer->getProductPrice($basePrice, $purchasePrice, $competitor);
// return a ProductPrice object with 11.76

```

## Output price

The pricer output a `ProductPrice` object with properties:

### sellingPrice

Pricer output to use  as a discounted price

### type

Contain one of the following possible type

| Constant                 | type value                     |
|--------------------------|--------------------------------|
| ProductPrice::BASE       | Unmodified base price          |
| ProductPrice::COMPETITOR | Aligned to competitor          |
| ProductPrice::MIN        | Minimal selling markup         |
| ProductPrice::TARGET     | Target selling markup          |
| ProductPrice::MIN_RATED  | Max price drop from base price |

### error

Contain an exception if sellingPrice is higher than target price or lower than align price 


## price calculation

### Markups for price computed from purchase price

__Align markup__: This is the markup on selling price in percentage used to compute the lower limit of a price aligned to competitor price

__Target markup__: This is the markup on selling price in percentage used to compute the price when there is no competitor


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

If purcharse price is unknown, the target price and align price can not be computed. The pricer compute a discounted price from a base price, when a competitor is lower than the base price, the pricer compute a lower price aligned to best competitor is the limit defined by the drop rate.

Allow a price drop up to 10%:

```php
$pricer->setDropRate(10);
```

| Base price | Competitive price | Pricer output |
|-----------:|------------------:|--------------:|
|      10.00 |             10.83 |         10.82 |
|      10.00 |              9.00 |          9.00 |
|      10.00 |              8.91 |          9.00 |


### Alignement to a competitor

When a competitive price is within allowed price update range, the default behaviour is the output a price lower than the best competitive price by 0.01 this can be modified to increase the price drop:


```php
$pricer->setBestCompetitorGap(0.03);
```

### Other options

Disable competitor aligment:

```php
$pricer->setAlignMarkup(null);
```

Disable price decrease to target price, in this case only an error message remain in the error property of the price object to indicate a base price higher than expected:


```php
$pricer->setDecreaseToTarget(false);
```


## Shipping price/Shipping cost


### Set the shipping cost base (real cost of your shipping)

The shipping cost is the real cost of the shipping.
The computed shipping price can be different thant the shipping cost. 

```php
$pricer->setShippingCost(5.99);
```
the different between shipping cost and shipping price is accounted in the pricer output.


### Set a shipping scale

A shipping scale is an array with a higher limit and a shipping amount. this will be used to compute the shipping price

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

The same fee rate is used to compute fees on price and fees on shipping cost

Apply fees on pricer output with a fee rate on percentage:

```php
$pricer->setFeeRate(10);

```


To disable fees on shipping cost, the setShippingFee method can be used:

```php
$pricer->setFeeOnShipping(false);

```
