<?php
namespace Pricer;

use PHPUnit\Framework\TestCase;

class PricerTest extends TestCase
{
    protected function assertPrice(float $expected, WinningPrice $current)
    {
        $this->assertEquals($expected, $current->value, 'Price type = '.$current->type, 0.01);
    }

    protected function getNoshippingPricer() : Pricer
    {
        $pricer = new Pricer();

        $pricer
            ->setAlignMarkup(18)
            ->setTargetMarkup(30)
            ->setDropRate(10);

        return $pricer;
    }

    protected function getFeesPricer() : Pricer
    {
        $pricer = new Pricer();

        $pricer->setFeeRate(15)
            ->setShippingCost(5.99)
            ->setShippingScale(
                [
                    [20,    5.99],
                    [70,    2.99],
                    [null,  0]
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
     * No fees, No shipping cost
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
        $this->assertPrice(14.50, $price);
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

        $this->assertTrue(isset($price->competitorPrice));
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(10.89, $price);
    }

    public function testCompetitorHigherThanTarget()
    {
        $pricer = $this->getNoshippingPricer();

        $price = $pricer->getWinningPrice(19.35, 8.00, 12.90);

        $this->assertTrue(isset($price->competitorPrice));
        $this->assertEquals(WinningPrice::TARGET, $price->type);
        $this->assertPrice(11.43, $price);
    }

    public function testShippingCompetitorLessThanMin()
    {
        // shipping + fees = lower than min markup
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(19.35, 8.00, 10.90);
        $this->assertEquals(WinningPrice::MIN, $price->type);
        $this->assertPrice(12.53, $price);
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
        // coeff fees = 1.176470588
        // pour un montant entre 20 et 70
        // shipping = 3.4485
        // 18*1.428571429*1.176470588 + 3.4485

        $pricer = $this->getFeesPricer();
        $pricer->setFeeOnShipping(false);
        $price = $pricer->getWinningPrice(35.00, 18.00);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
        $this->assertPrice(33.70, $price);
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
     * < 10%
     */
    public function testNoSellingPriceCompetitorAllowed()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(35.00, null, 34.99);
        $this->assertEquals(WinningPrice::COMPETITOR, $price->type);
        $this->assertPrice(34.98, $price);
    }

    /**
     * > 10%
     */
    public function testNoSellingPriceCompetitorNotAllowed()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getWinningPrice(35.00, null, 14.00);
        $this->assertEquals(WinningPrice::MIN_RATED, $price->type);
        $this->assertPrice(31.50, $price);
    }

    /**
     * = 10%
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
     * Price is not modified
     */
    public function testDecreaseToTargetDisabled()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(null)
            ->setNoCompetitorPolicy(Pricer::BASE_PRICE);

        $price = $pricer->getWinningPrice(35.00, 6.00);
        $this->assertEquals(WinningPrice::BASE, $price->type);
    }


    /**
     * Force a markup
     * Price is modified according to purchase price
     */
    public function testForceMarkupOnPurchasePrice()
    {
        $pricer = $this->getNoshippingPricer()
            ->setAlignMarkup(10)
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(35.00, 9.00);

        $this->assertPrice(10.00, $price);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
    }


    /**
     * Force a markup
     * Price is not modified if no purchase price
     */
    public function testForceMarkupWithNoPurchasePrice()
    {
        $pricer = $this->getNoshippingPricer()
            ->setAlignMarkup(10)
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(35.00);

        $this->assertPrice(35.00, $price);
        $this->assertEquals(WinningPrice::BASE, $price->type);
    }


    public function testForceMarkupWithFees()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(10)
            ->setTargetMarkup(10);
        $price = $pricer->getWinningPrice(11.00, 7.00);

        $this->assertPrice(10.21, $price);
        $this->assertEquals(WinningPrice::TARGET, $price->type);

        // markup factor: 1,111111111
        // 7,77777
        // fee factor: 1,176470588
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
     * Test case when a output price higher than base price is allowed
     */
    public function testIncreasePriceWithTargetMarkup()
    {
        $pricer = $this->getFeesPricer()
        ->setNoCompetitorPolicy(Pricer::TARGET_PRICE);

        $price = $pricer->getWinningPrice(24.00, 20.00);
        $this->assertEquals(WinningPrice::TARGET, $price->type);
        $this->assertGreaterThan(24.00, $price->value);
    }

}
