<?php
namespace Pricer;

/**
 * Generic pricer without database connexion
 */
class Pricer
{
    /**
     * Real cost of shipping for the seller
     * @var float
     */
    protected $shippingCost = 0.0;

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
     * Always decrease to target price if price is higher than target selling markup
     * @var string
     */
    protected $decreaseToTarget = true;

    /**
     * factor to obtain the minimal selling price from purchase price
     * if null, disable alignement to competitor price
     * @var float
     */
    protected $alignMarkupFactor = 1;

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

    /**
     * Get target factor used to compute target price from the purchase price
     * @return float
     */
    public function getTargetMarkupFactor() : float
    {
        return $this->targetMarkupFactor;
    }

    /**
     * Set minimal selling markup if competitors
     * @param int $minMargin margin rate %
     * @return self
     */
    public function setAlignMarkup(int $minMargin = null) : self
    {
        if (!isset($minMargin)) {
            $this->alignMarkupFactor = null;

            return $this;
        }
        $this->alignMarkupFactor = $this->getMarkupFactor($minMargin);

        return $this;
    }

    /**
     * Get minimal align factor used to compute minimal price from purchase price
     * @return float
     */
    public function getAlignMarkupFactor() : float
    {
        return $this->alignMarkupFactor;
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
     * Allowed drop factor if no purchase price
     * @return float
     */
    public function getDropFactor() : float
    {
        return $this->dropRateFactor;
    }

    /**
     * Get minimal selling price
     * @param float $purchasePrice
     * @return float
     */
    public function getMinPrice(float $purchasePrice) : float
    {
        if (!isset($this->alignMarkupFactor)) {
            return $this->getTargetPrice($purchasePrice);
        }

        $minPrice = $purchasePrice * $this->alignMarkupFactor * $this->feeFactor;
        $minPrice += $this->getShippingPrice($minPrice);

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
        $targetPrice += $this->getShippingPrice($targetPrice);

        return round($targetPrice, 2);
    }

    /**
     * Set the real shipping cost paid by seller
     * @param float $shippingCost
     * @return self
     */
    public function setShippingCost(float $shippingCost) : self
    {
        $this->shippingCost = $shippingCost;

        return $this;
    }

    /**
     * Get the real shipping cost paid by seller
     * @return float
     */
    public function getShippingCost() : float
    {
        return $this->shippingCost;
    }

    /**
     * Get computed shipping cost
     * @param float $sellingPrice   A selling price with all fees included except shipping
     */
    public function getShippingPrice(float $sellingPrice) : float
    {
        if (count($this->shippingScale) > 0 && 0 === (int) round(100 * $this->getShippingCost())) {
            throw new \Exception('Shipping cost is required with a shipping scale');
        }

        foreach ($this->shippingScale as $scale) {
            $shipping =
                (($this->getShippingCost() * $this->getFeeFactor()) - $scale[1])
                / $this->getFeeFactor();

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
        $estimatedCents = (int) round(100 * ($competitor->sellingPrice - $this->bestCompetitorGap));

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


        if (isset($competitor) && isset($this->alignMarkupFactor)) {
            $price->competitor = $competitor;

            if ($this->canUseCompetitor($competitor, $minPrice)) {
                $price->setSellingPriceDown(
                    round($competitor->sellingPrice - $this->bestCompetitorGap, 2),
                    ProductPrice::COMPETITOR
                );
            } else {
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
