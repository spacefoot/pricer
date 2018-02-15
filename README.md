# pricer

The pricer is a tool used to compute a discounted price according to differents parameters.

## General purpose

This sofware is designed to compute a beter price on products using defined markups and competitors price on the same product. The pricer will never output a price higher than the current price to ensure that special discounts are retained as is.


## price calculation

### Markups for price computed from purchase price

Align markup: This is the markup on selling price in percentage used to compute the lower limit of a price aligned to competitor price

Target markup: This is the markup on selling price in percentage used to compute the price when there is no competitor


```php
$pricer->setAlignMarkup(10);
$pricer->setTargetMarkup(10);
```

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


## Shipping price/Shipping cost


### Set the shipping cost base (real cost of your shipping)

The shipping cost is the real cost of the shipping.
The computed shipping price can be different thant the shipping cost. 

```php
$pricer->setShippingCost(5.99);
```
the different between shipping cost and shipping price is accounted in the pricer output.


### Set a shipping scale

A shipping scale is an array with a higher limit and a shipping amout. this will be used to compute the shipping price

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

## Fees

The same fee rate is used to compute fees on price and fees on shipping cost

To disable fees on shipping cost, the setShippingFee method can be used:

```php
$pricer->setShippingFee(false);

```
