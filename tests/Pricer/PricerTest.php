<?php
namespace Pricer;

use PHPUnit\Framework\TestCase;

class PricerTest extends TestCase
{
    protected function assertPrice(float $expected, ProductPrice $current)
    {
        $this->assertEquals($expected, $current->sellingPrice, 'Price type = '.$current->type, 0.01);
    }

    protected function getNoshippingPricer() : Pricer
    {
        $pricer = new Pricer();

        $pricer
            ->setAlignMarkup(18)
            ->setTargetSellingMarkup(30)
            ->setDropRate(10);

        return $pricer;
    }

    protected function getFeesPricer() : Pricer
    {
        $pricer = new Pricer();

        $pricer->setFeeSellingRate(15)
            ->setShippingCost(5.99)
            ->setShippingScale(
                [
                    [20,    5.99],
                    [70,    2.99],
                    [null,  0]
                ]
            )
            ->setShippingFee(true)
            ->setAlignMarkup(18)
            ->setTargetSellingMarkup(30)
            ->setDropRate(10);

        return $pricer;
    }

    protected function getCompetitor(float $price)
    {
        $competitor = new Competitor();
        $competitor->sellingPrice = $price;
        $competitor->marketplace = 'Amazon FR';

        return $competitor;
    }

    public function testNoChange()
    {
        $pricer = new Pricer();
        $price = $pricer->getProductPrice(19.35);
        $this->assertPrice(19.35, $price);

        $price = $this->getNoshippingPricer()->getProductPrice(19.35);
        $this->assertPrice(19.35, $price);

        $price = $this->getFeesPricer()->getProductPrice(19.35);
        $this->assertPrice(19.35, $price);
    }

    /**
     * No fees, No shipping cost
     */
    public function testPurchasePriceNoCompetitor()
    {
        $price = $this->getNoshippingPricer()->getProductPrice(19.35, 8.00);
        $this->assertPrice(11.43, $price);
    }

    public function testHighPurchasePriceNoCompetitor()
    {
        $price = $this->getNoshippingPricer()->getProductPrice(19.35, 18.00);
        $this->assertPrice(19.35, $price);
    }

    public function testPurchasePriceNoCompetitorFees()
    {
        $price = $this->getFeesPricer()->getProductPrice(19.35, 8.00);
        $this->assertPrice(14.50, $price);
    }

    public function testHighPurchasePriceNoCompetitorFees()
    {
        $price = $this->getFeesPricer()->getProductPrice(19.35, 18.00);
        $this->assertPrice(19.35, $price);
    }

    public function testCompetitorHigherThanMin()
    {
        $pricer = $this->getNoshippingPricer();

        $price = $pricer->getProductPrice(19.35, 8.00, $this->getCompetitor(10.90));

        $this->assertTrue(isset($price->competitor));
        $this->assertEquals(ProductPrice::COMPETITOR, $price->type);
        $this->assertPrice(10.89, $price);
    }

    public function testCompetitorHigherThanTarget()
    {
        $pricer = $this->getNoshippingPricer();

        $price = $pricer->getProductPrice(19.35, 8.00, $this->getCompetitor(12.90));

        $this->assertTrue(isset($price->competitor));
        $this->assertEquals(ProductPrice::TARGET, $price->type);
        $this->assertPrice(11.43, $price);
    }

    public function testShippingCompetitorLessThanMin()
    {
        // shipping + fees = lower than min markup
        $pricer = $this->getFeesPricer();

        $price = $pricer->getProductPrice(19.35, 8.00, $this->getCompetitor(10.90));
        $this->assertEquals(ProductPrice::MIN, $price->type);
        $this->assertPrice(12.53, $price);
    }

    public function testShippingCompetitorHigherThanMin()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getProductPrice(19.35, 8.00, $this->getCompetitor(13.90));
        $this->assertEquals(ProductPrice::COMPETITOR, $price->type);
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
        $pricer->setShippingFee(false);
        $price = $pricer->getProductPrice(35.00, 18.00);
        $this->assertEquals(ProductPrice::TARGET, $price->type);
        $this->assertPrice(33.70, $price);
    }


    public function testNoSellingPriceNoCompetitor()
    {
        $pricer = $this->getFeesPricer();
        $price = $pricer->getProductPrice(35.00, null);
        $this->assertEquals(ProductPrice::BASE, $price->type);
        $this->assertPrice(35.00, $price);
    }

    /**
     * < 10%
     */
    public function testNoSellingPriceCompetitorAllowed()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getProductPrice(35.00, null, $this->getCompetitor(34.99));
        $this->assertEquals(ProductPrice::COMPETITOR, $price->type);
        $this->assertPrice(34.98, $price);
    }

    /**
     * > 10%
     */
    public function testNoSellingPriceCompetitorNotAllowed()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getProductPrice(35.00, null, $this->getCompetitor(14.00));
        $this->assertEquals(ProductPrice::MIN_RATED, $price->type);
        $this->assertPrice(31.50, $price);
    }

    /**
     * = 10%
     */
    public function testNoSellingPriceCompetitorLess10Percent()
    {
        $pricer = $this->getFeesPricer();

        $price = $pricer->getProductPrice(35.00, null, $this->getCompetitor(31.51));
        $this->assertEquals(ProductPrice::COMPETITOR, $price->type);
        $this->assertPrice(31.50, $price);
    }



    public function testAlignDisabled()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(null)
            ->setDecreaseToTarget(true);

        $competitor = $this->getCompetitor(14.00);

        $price = $pricer->getProductPrice(35.00, null, $competitor);
        $this->assertEquals(ProductPrice::BASE, $price->type);

        $price = $pricer->getProductPrice(35.00, 6.00, $competitor);
        $this->assertEquals(ProductPrice::TARGET, $price->type);
    }

    /**
     * base price higher than target markup price, with decrease to target disabled
     * Price contain error, price is not modified
     */
    public function testDecreaseToTargetDisabled()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(null)
            ->setDecreaseToTarget(false);

        $price = $pricer->getProductPrice(35.00, 6.00);
        $this->assertInstanceOf('Pricer\UnexpectedPriceException', $price->error);
        $this->assertEquals(ProductPrice::BASE, $price->type);
    }


    /**
     * Force a markup
     * Price is modified according to purchase price
     */
    public function testForceMarkupOnPurchasePrice()
    {
        $pricer = $this->getNoshippingPricer()
            ->setAlignMarkup(10)
            ->setTargetSellingMarkup(10);
        $price = $pricer->getProductPrice(35.00, 9.00);

        $this->assertPrice(10.00, $price);
        $this->assertEquals(ProductPrice::TARGET, $price->type);
    }


    /**
     * Force a markup
     * Price is not modified if no purchase price
     */
    public function testForceMarkupWithNoPurchasePrice()
    {
        $pricer = $this->getNoshippingPricer()
            ->setAlignMarkup(10)
            ->setTargetSellingMarkup(10);
        $price = $pricer->getProductPrice(35.00);

        $this->assertPrice(35.00, $price);
        $this->assertEquals(ProductPrice::BASE, $price->type);
    }


    public function testForceMarkupWithFees()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(10)
            ->setTargetSellingMarkup(10);
        $price = $pricer->getProductPrice(11.00, 7.00);

        $this->assertPrice(10.21, $price);
        $this->assertEquals(ProductPrice::TARGET, $price->type);

        // markup factor: 1,111111111
        // 7,77777
        // fee factor: 1,176470588
        // 9.150235292
        // shipping 0.8985 * 1.176470588
        // 9.150235292 + 1,057058823
        // 10,207294115
    }


    public function testShippingCostGreaterThanBasePrice()
    {
        $pricer = $this->getFeesPricer()
            ->setAlignMarkup(10)
            ->setTargetSellingMarkup(10);
        $price = $pricer->getProductPrice(6.99, 7.00);

        $this->assertInstanceOf('Pricer\UnexpectedPriceException', $price->error);
        $this->assertEquals(ProductPrice::BASE, $price->type);
    }

    public function testfeesUpdate()
    {
        $pricer15 = $this->getFeesPricer()->setFeeSellingRate(15);
        $pricer16 = $this->getFeesPricer()->setFeeSellingRate(16);

        $price15 = $pricer15->setShippingFee(false)->getProductPrice(14.00, 6.00);
        $price15s = $pricer15->setShippingFee(true)->getProductPrice(14.00, 6.00);

        $price16 = $pricer16->setShippingFee(false)->getProductPrice(14.00, 6.00);
        $price16s = $pricer16->setShippingFee(true)->getProductPrice(14.00, 6.00);

        $this->assertLessThan($price15s->sellingPrice, $price15->sellingPrice);
        $this->assertLessThan($price16s->sellingPrice, $price16->sellingPrice);

        $this->assertLessThan($price16->sellingPrice, $price15->sellingPrice);
        $this->assertLessThan($price16s->sellingPrice, $price15s->sellingPrice);
    }
}
