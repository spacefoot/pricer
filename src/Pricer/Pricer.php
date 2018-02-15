<?php
namespace Pricer;

/**
 * Generic pricer without database connexion
 */
class Pricer
{
    /**
     * Shipping scale used to ccompute shipping cost
     * @var array
     */
    protected $shippingScale = [];

    /**
     * Use fee on shipping cost
     * @var bool
     */
    protected $shippingFee = false;

    /**
     * Set the lowest price possible if alignement to a competitor price is not possible
     * @var string
     */
    protected $decreaseIfLowCompetitor = true;

    /**
     * Always decrease to target price if price is higher than target selling markup
     * @var string
     */
    protected $decreaseToTarget = true;

    /**
     * factor to obtain the minimal selling price from purchase price
     * @var float
     */
    protected $minMarkupFactor = 1;

    /**
     * factor to obtain the desired selling price from purchase price
     * @var int
     */
    protected $targetMarkupFactor = 1;

    /**
     * Drop factor on price for products without competitor (10% allowed)
     * @var float
     */
    protected $dropRateFactor = 0.9;

    /**
     * fee factor if fees are used on selling price
     * @var float
     */
    protected $feeFactor = 1;

    /**
     * Gap with best competitor in euro when we set a lower price
     * @var float
     */
    protected $bestCompetitorGap = 0.01;

    /**
     * Get factor from selling markup (taux de marque)
     * to apply on purchase price
     * @param int $rate
     * @return float
     */
    private function getMarkupFactor(int $rate) : float
    {
        return 100/(100-$rate);
    }


    /**
     * @param float $fee       Fee percentage
     * @return self
     */
    public function setFeeSellingMarkup(int $fee) : self
    {
        $this->feeFactor = $this->getMarkupFactor($fee);

        return $this;
    }

    public function getFeeFactor() : float
    {
        return $this->feeFactor;
    }

    /**
     * Optional config, default is 0.01
     * @param float $amount Euros
     * @return self
     */
    public function setBestCompetitorGap(float $amount) : self
    {
        $this->bestCompetitorGap = $amount;

        return $this;
    }


    /**
     * Set shipping object used to compute shipping cost
     * First value is the upper limit to compare with total selling price
     * Second value is the amount of shipping
     * [
     *   [20,    0.8985],
     *   [70,    3.4485],
     *   [null,  5.9900]
     * ]
     * @param array $shippingScale
     * @return self
     */
    public function setShippingScale(array $shippingScale) : self
    {


        $this->shippingScale = $shippingScale;

        return $this;
    }

    /**
     * Enable/Disable fees on shipping cost
     * @param boolean $shippingFee
     * @return self
     */
    public function setShippingFee(bool $shippingFee) : self
    {
        $this->shippingFee = $shippingFee;

        return $this;
    }

    /**
     * Set the lowest price possible if alignement to a competitor price is not possible, Enable/Disable
     *
     * @todo replace setAlignMarkup(null)
     *
     * @param boolean $decrease
     * @return self
     */
    public function setDecreaseIfLowCompetitor(bool $decrease) : self
    {
        $this->decreaseIfLowCompetitor = $decrease;

        return $this;
    }

    /**
     * Enable/Disable always decrease to target price if price is higher than target selling markup
     * @param boolean $decrease
     * @return self
     */
    public function setDecreaseToTarget(bool $decrease) : self
    {
        $this->decreaseToTarget = $decrease;

        return $this;
    }

    /**
     * Set desired selling markup if no competitors
     * @param int $targetMargin Selling markup in percentage
     * @return self
     */
    public function setTargetSellingMarkup(int $targetMargin) : self
    {
        $this->targetMarkupFactor = $this->getMarkupFactor($targetMargin);

        return $this;
    }

    public function getTargetMarkupFactor() : float
    {
        return $this->targetMarkupFactor;
    }

    /**
     * Set minimal selling markup if competitors
     *
     * @todo rename setAlignMarkup
     *
     * @param int $minMargin margin rate %
     * @return self
     */
    public function setMinSellingMarkup(int $minMargin) : self
    {
        $this->minMarkupFactor = $this->getMarkupFactor($minMargin);

        return $this;
    }

    public function getMinMarkupFactor() : float
    {
        return $this->minMarkupFactor;
    }

    /**
     * Allowed drop rate on price for products without competitor
     * @param int $rate
     * @return self
     */
    public function setDropRate(int $rate) : self
    {
        $this->dropRateFactor = (100-$rate)/100;

        return $this;
    }

    /**
     * Get minimal selling price
     * @param float $purchasePrice
     * @return float
     */
    public function getMinPrice(float $purchasePrice) : float
    {
        $minPrice = $purchasePrice * $this->minMarkupFactor * $this->feeFactor;
        $minPrice += $this->getShippingCost($minPrice);

        return round($minPrice, 2);
    }

    /**
     * Get target selling price
     * @param float $purchasePrice
     * @return float
     */
    public function getTargetPrice(float $purchasePrice) : float
    {
        $targetPrice = $purchasePrice * $this->targetMarkupFactor * $this->feeFactor;
        $targetPrice += $this->getShippingCost($targetPrice);

        return round($targetPrice, 2);
    }


    /**
     * Get computed shipping cost
     * @param float $sellingPrice   A selling price with all fees included except shipping
     */
    public function getShippingCost(float $sellingPrice) : float
    {
        if (!isset($this->shippingScale[0])) {
            return 0.0;
        }

        //TODO add method for base shipping cost
        $baseShippingCost = $this->shippingScale[0][1];

        foreach ($this->shippingScale as $scale) {

            $shipping = (($baseShippingCost * $this->getFeeFactor()) - $scale[1]) / $this->getFeeFactor();

            if ($this->shippingFee) {
                $shipping *= $this->getFeeFactor();
            }

            if (!isset($scale[0])) {
                return $shipping;
            }

            $price = round($sellingPrice + $shipping, 2);
            if ($price < $scale[0]) {
                return $shipping;
            }
        }

        return 0.0;
    }


    /**
     * Can use competitor if competitor price - 0.01 > min price or if competitor price >= selling price x 0.9
     * @param Competitor $competitor
     * @param float $minPrice
     *
     * @return bool
     */
    protected function canUseCompetitor(Competitor $competitor, float $minPrice) : bool
    {
        $estimatedCents = (int) round(100 *($competitor->sellingPrice - $this->bestCompetitorGap));

        return ($estimatedCents >= (int) round(100 * $minPrice));
    }


    /**
     * Lower price to target price if necessary, check price validity
     * @param ProductPrice $price
     * @param float $minPrice
     * @param float $targetPrice
     * @throws UnexpectedPriceException
     */
    protected function normalizePrice(ProductPrice $price, float $minPrice, float $targetPrice = null)
    {
        if (isset($targetPrice)) {
            if ($this->decreaseToTarget) {
                // If the price remain higher than target price, normalize
                $price->setSellingPriceDown($targetPrice, ProductPrice::TARGET);

            } elseif ($price->sellingPrice > $targetPrice) {
                throw new UnexpectedPriceException(
                    sprintf('Current selling price %f higher than target price %f', $price->sellingPrice, $targetPrice)
                );
            }
        }

        if ($price->sellingPrice < $minPrice) {
            throw new UnexpectedPriceException(
                sprintf('Current selling price %f lower than minimal price %f', $price->sellingPrice, $minPrice)
            );
        }
    }


    /**
     * Compute a product price
     *
     * @param float         $basePrice              Store 0 price, never modified by the pricer
     * @param float         $purchasePrice          Can be null
     * @param Competitor    $competitor             Can be null
     *
     * @return ProductPrice
     */
    public function getProductPrice(float $basePrice, float $purchasePrice = null, Competitor $competitor = null) : ProductPrice
    {
        $price = new ProductPrice();
        $price->sellingPrice = $basePrice;
        $price->type = ProductPrice::BASE;

        $targetPrice = null;
        $minPrice = $basePrice * $this->dropRateFactor;

        if (isset($purchasePrice)) {
            $targetPrice = $this->getTargetPrice($purchasePrice);
            $minPrice = $this->getMinPrice($purchasePrice);
        }


        if (isset($competitor)) {
            $price->competitor = $competitor;

            if ($this->canUseCompetitor($competitor, $minPrice)) {
                $price->setSellingPriceDown(
                    round($competitor->sellingPrice - $this->bestCompetitorGap, 2),
                    ProductPrice::COMPETITOR
                );
            } elseif ($this->decreaseIfLowCompetitor) {
                $price->setSellingPriceDown(
                    round($minPrice, 2),
                    isset($purchasePrice) ? ProductPrice::MIN : ProductPrice::MIN_RATED
                );
            }
        }

        try {
            $this->normalizePrice($price, $minPrice, $targetPrice);
        } catch (UnexpectedPriceException $e) {
            $price->error = $e;
        }

        return $price;
    }
}
