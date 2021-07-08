<?php

namespace Pricer;

use PHPUnit\Framework\TestCase;

class PricerTest extends TestCase
{
    protected function assertPrice(float $expected, WinningPrice $current)
    {
        $this->assertEquals($expected, $current->value, 'Price type = '.$current->type, 0.01);
    }

    protected function getNoshippingPricer(): Pricer
    {
        $pricer = new Pricer();

        $pricer
            ->setAlignMarkup(18)
            ->setTargetMarkup(30)
            ->setDropRate(10);

        return $pricer;
    }

    protected function getFeesPricer(): Pricer
    {
        $pricer = new Pricer();

        $pricer->setFeeRate(15)
            ->setShippingCost(5.99)
            ->setShippingScale(
                [
                    [20,    5.99],
                    [70,    2.99],
                    [null,  0],
                ]
            )
            ->setFeeOnShipping(true)
            ->setAlignMarkup(18)
            ->setTargetMarkup(30)
            ->setDropRate(10);

        return $pricer;
    }

    public function testNoChange()
    {
        $pricer = new Pricer();
        $price = $pricer->getWinningPrice(19.35);
        $this->assertPrice(19.35, $price);

        $price = $this->getNoshippingPricer()->getWinningPrice(19.35);
        $this->assertPrice(19.35, $price);

        $price = $this->getFeesPricer()->getWinningPrice(19.35);
        $this->assertPrice(19.35, $price);
    }

    /**
     * No fees, No shipping cost.
     */
    public function testPurchasePriceNoCompetitor()
    {
        $price = $this->getNoshippingPricer()->getWinningPrice(19.35, 8.00);
        $this->assertPrice(11.43, $price);
    }

    public function testHighPurchasePriceNoCompetitor()
    {
        $price = $this->getNoshippingPricer()->getWinningPrice(19.35, 18.00);
        $this->assertPrice(19.35, $price);
    }

    public function testPurchasePriceNoCompetitorFees()
    {
        $price = $this->getFeesPricer()->getWinningPrice(19.35, 8.00);
        $this->assertPrice(14.04, $price);
    }

    public function testHighPurchasePriceNoCompetitorFees()
    {
        $price = $this->getFeesPricer()->getWinningPrice(19.35, 18.00);
        $this->assertPrice(19.35, $price);
    }

    public function testCompetitorHigherThanMin()
    {
        $pricer = $this->getNoshippingPricer();

        $price = $pricer->getWinningPrice(19.35, 8.00, 10.90);
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(10.89, $price);
    }

    public function testCompetitorHigherThanTarget()
    {
        $pricer = $this->getNoshippingPricer();

        $price = $pricer->getWinningPrice(19.35, 8.00, 12.90);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
        $this->assertPrice(11.43, $price);
    }

    public function testShippingCompetitorLessThanMin()
    {
        // shipping + fees = lower than align markup
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(19.35, 8.00, 10.90);
        $this->assertEquals(WinningPrice::MIN, $price->type);
        $this->assertPrice(12.12, $price);
    }

    public function testShippingCompetitorHigherThanMin()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(19.35, 8.00, 13.90);
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(13.89, $price);
    }

    public function testShippingCostNoFees()
    {
        // coeff taux de marque cible = 1.428571429
        // coeff fees = 1.15
        // pour un montant entre 20 et 70
        // shipping = 3.4485
        // 18*1.428571429*1.176470588 + 3.4485

        $pricer = $this->getFeesPricer();
        $pricer->setFeeOnShipping(false);
        $price = $pricer->getWinningPrice(35.00, 18.00);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
        $this->assertPrice(32.96, $price);
    }

    public function testNoSellingPriceNoCompetitor()
    {
        $pricer = $this->getFeesPricer();
        $price = $pricer->getWinningPrice(35.00, null);
        $this->assertEquals(WinningPrice::BASE, $price->type);
        $this->assertPrice(35.00, $price);
    }

    public function testCompetitorGap()
    {
        $pricer = $this->getFeesPricer();
        $pricer->setCompetitorGap(0.02);

        $price = $pricer->getWinningPrice(35.00, null, 34.99);
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(34.97, $price);
    }

    /**
     * < 10%.
     */
    public function testNoSellingPriceCompetitorAllowed()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(35.00, null, 34.99);
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(34.98, $price);
    }

    /**
     * > 10%.
     */
    public function testNoSellingPriceCompetitorNotAllowed()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(35.00, null, 14.00);
        $this->assertEquals(WinningPrice::MIN_RATED, $price->type);
        $this->assertPrice(31.50, $price);
    }

    /**
     * = 10%.
     */
    public function testNoSellingPriceCompetitorLess10Percent()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(35.00, null, 31.51);
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(31.50, $price);
    }

    public function testAlignDisabled()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(null)
            ->setCompetitorPolicy(Pricer::NO_ALIGN);

        $competitor = 14.00;

        $price = $pricer->getWinningPrice(35.00, null, $competitor);
        $this->assertEquals(WinningPrice::BASE, $price->type);

        $price = $pricer->getWinningPrice(35.00, 6.00, $competitor);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
    }

    /**
     * base price higher than target markup price, with decrease to target disabled
     * Price is not modified.
     */
    public function testDecreaseToTargetDisabled()
    {
        $pricer = $this->getFeesPricer()
            ->setNoCompetitorPolicy(Pricer::BASE_PRICE);

        $price = $pricer->getWinningPrice(35.00, 6.00);
        $this->assertEquals(WinningPrice::BASE, $price->type);
    }

    /**
     * Force a markup
     * Price is modified according to purchase price.
     */
    public function testForceMarkupOnPurchasePrice()
    {
        $pricer = $this->getNoshippingPricer()
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(35.00, 9.00);

        $this->assertPrice(10.00, $price);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
    }

    /**
     * Force a markup, disable alignement.
     */
    public function testForceMarkupWithCompetitor()
    {
        $pricer = $this->getNoshippingPricer()
            ->setCompetitorPolicy(Pricer::NO_ALIGN)
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(35.00, 9.00, 9.99);

        $this->assertPrice(10.00, $price);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
    }

    /**
     * Force a markup
     * Price is not modified if no purchase price.
     */
    public function testForceMarkupWithNoPurchasePrice()
    {
        $pricer = $this->getNoshippingPricer()
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(35.00);

        $this->assertPrice(35.00, $price);
        $this->assertEquals(WinningPrice::BASE, $price->type);
    }

    public function testForceMarkupWithFees()
    {
        $pricer = $this->getFeesPricer()
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(11.00, 7.00);

        $this->assertPrice(9.84, $price);
        $this->assertEquals(WinningPrice::TARGET, $price->type);

        // markup factor: 1,111111111
        // 7,77777
        // fee factor: 1,15
        // 9.150235292
        // shipping 0.8985 * 1.176470588
        // 9.150235292 + 1,057058823
        // 10,207294115
    }

    public function testPurchasePriceGreaterThanBasePrice()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(10)
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(6.99, 7.00);

        $this->assertEquals(WinningPrice::BASE, $price->type);
    }

    public function testfeesUpdate()
    {
        $pricer15 = $this->getFeesPricer()->setFeeRate(15);
        $pricer16 = $this->getFeesPricer()->setFeeRate(16);

        $price15 = $pricer15->setFeeOnShipping(false)->getWinningPrice(14.00, 6.00);
        $price15s = $pricer15->setFeeOnShipping(true)->getWinningPrice(14.00, 6.00);

        $price16 = $pricer16->setFeeOnShipping(false)->getWinningPrice(14.00, 6.00);
        $price16s = $pricer16->setFeeOnShipping(true)->getWinningPrice(14.00, 6.00);

        $this->assertLessThan($price15s->value, $price15->value);
        $this->assertLessThan($price16s->value, $price16->value);

        $this->assertLessThan($price16->value, $price15->value);
        $this->assertLessThan($price16s->value, $price15s->value);
    }

    public function test3Competitors()
    {
        $pricer = $this->getNoshippingPricer()
        ->setAlignMarkup(10)
        ->setTargetMarkup(20);

        $this->assertPrice(11.11, $pricer->getWinningPrice(15.00, 10.00, 11.00));
        $this->assertPrice(11.99, $pricer->getWinningPrice(15.00, 10.00, 12.00));
        $this->assertPrice(12.50, $pricer->getWinningPrice(15.00, 10.00, 13.00));
        $this->assertPrice(12.50, $pricer->getWinningPrice(15.00, 10.00));
    }

    /**
     * Test case when a output price higher than base price is allowed.
     */
    public function testIncreasePriceWithTargetMarkup()
    {
        $pricer = $this->getFeesPricer()
        ->setNoCompetitorPolicy(Pricer::TARGET_PRICE);

        $price = $pricer->getWinningPrice(24.00, 20.00);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
        $this->assertGreaterThan(24.00, $price->value);
    }

    public function testNoTargetMarkup()
    {
        $pricer = $this->getFeesPricer()->setTargetMarkup(null);

        $this->expectException(\Exception::class);
        $pricer->getWinningPrice(24.00, 20.00);
    }

    public function testFloatTargetMarkup()
    {
        $basePrice = 900000.00;
        $purchasePrice = 500000.00;

        $pricer1 = new Pricer();
        $pricer2 = new Pricer();

        $pricer1->setTargetMarkup(20.1);
        $pricer2->setTargetMarkup(20.9);

        $p1 = $pricer1->getWinningPrice($basePrice, $purchasePrice);
        $p2 = $pricer2->getWinningPrice($basePrice, $purchasePrice);

        $this->assertGreaterThan($p1->value, $p2->value);
    }

    public function testFloatAlignMarkup()
    {
        $basePrice = 900000.00;
        $purchasePrice = 500000.00;
        $competitorPrice = 310000.00;

        $pricer1 = new Pricer();
        $pricer2 = new Pricer();

        $pricer1->setTargetMarkup(20.00)->setAlignMarkup(15.6);
        $pricer2->setTargetMarkup(20.00)->setAlignMarkup(15.9);

        $p1 = $pricer1->getWinningPrice($basePrice, $purchasePrice, $competitorPrice);
        $p2 = $pricer2->getWinningPrice($basePrice, $purchasePrice, $competitorPrice);

        $this->assertGreaterThan($p1->value, $p2->value);
    }

    public function testFloatFeeRate()
    {
        $basePrice = 900000.00;
        $purchasePrice = 500000.00;

        $pricer1 = $this->getFeesPricer()->setFeeRate(15.1);
        $pricer2 = $this->getFeesPricer()->setFeeRate(15.9);

        $p1 = $pricer1->getWinningPrice($basePrice, $purchasePrice);
        $p2 = $pricer2->getWinningPrice($basePrice, $purchasePrice);

        $this->assertGreaterThan($p1->value, $p2->value);
    }

    public function testNegativeTargetMarkup()
    {
        $pricer = new Pricer();
        $pricer->setTargetMarkup(-100);

        $basePrice = 20;
        $purchasePrice = 10;

        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);

        $this->assertEquals($winningPrice->value, 5);
    }

    public function testNegativeAlignMarkup()
    {
        $pricer = new Pricer();
        $pricer->setTargetMarkup(-100);
        $pricer->setAlignMarkup(-200);

        $basePrice = 20;
        $purchasePrice = 10;
        $competitorPrice = 2;

        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice, $competitorPrice);

        $this->assertEquals($winningPrice->value, 3.33);
    }

    public function testBasePriceBelowMinMarkup()
    {
        $pricer = new Pricer();
        $pricer->setAlignMarkup(25);
        $pricer->setTargetMarkup(30);
        $pricer->setNoCompetitorPolicy(Pricer::BASE_PRICE);

        $basePrice = 9;
        $purchasePrice = 9;

        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 9);

        $pricer->setMinMarkup(10);
        $pricer->setRaiseBasePriceIfBelowMinMarkup(true);
        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 10);
        $this->assertEquals(WinningPrice::BASE_RAISED, $winningPrice->type);

        $purchasePrice = 5;
        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 9);
        $this->assertEquals(WinningPrice::BASE, $winningPrice->type);

        $pricer
            ->setMinMarkup(25)
            ->setShippingCost(5.99)
            ->setShippingScale(
                [
                    [20,    5.99],
                    [70,    2.99],
                    [null,  0],
                ]
            );
        $basePrice = 140;
        $purchasePrice = 132.92;
        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 183.22);
        $this->assertEquals(WinningPrice::BASE_RAISED, $winningPrice->type);

        $pricer->setShippingScale(
            [
                [50,    5.99],
                [75,    4.99],
                [null,  0],
            ]
        );
        $basePrice = 45.50;
        $purchasePrice = 43.19;
        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 58.59);
        $this->assertEquals(WinningPrice::BASE_RAISED, $winningPrice->type);

        $basePrice = 12;
        $purchasePrice = 11.36;
        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 15.15);
        $this->assertEquals(WinningPrice::BASE_RAISED, $winningPrice->type);

        $basePrice = 15.15;
        $purchasePrice = 11.36;
        $winningPrice = $pricer->getWinningPrice($basePrice, $purchasePrice);
        $this->assertEquals($winningPrice->value, 15.15);
        $this->assertEquals(WinningPrice::BASE, $winningPrice->type);
    }
}
