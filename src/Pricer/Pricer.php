<?php
namespace Pricer;

/**
 * Generic pricer without database connexion
 */
class Pricer
{
    /**
     * competitor policy, do not align to competitor price
     * @var integer
     */
    const NO_ALIGN = 0;

    /**
     * competitor policy, align to competito price
     * @var integer
     */
    const ALIGN = 1;

    /**
     * No competitor policy, return base price
     * @var integer
     */
    const BASE_PRICE = 0;

    /**
     * No competitor policy, always decrease to target price if price is higher than target selling markup
     * @var integer
     */
    const TARGET_BELOW_BASE_PRICE = 1;

    /**
     * No competitor policy, always change price to target price
     * @var integer
     */
    const TARGET_PRICE = 2;


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
    protected $feeOnShipping = false;

    /**
     * @var int
     */
    protected $competitorPolicy = self::ALIGN;

    /**
     * @var int
     */
    protected $noCompetitorPolicy = self::TARGET_BELOW_BASE_PRICE;

    /**
     * Align selling markup percentage, can be null
     * @var int | null
     */
    protected $alignMarkup = null;

    /**
     * factor to obtain the minimal selling price from purchase price
     * if null, disable alignement to competitor price
     * @var float
     */
    protected $alignMarkupFactor = null;

    /**
     * target selling markup percentage
     * @var int
     */
    protected $targetMarkup = 0;

    /**
     * factor to obtain the desired selling price from purchase price
     * @var int
     */
    protected $targetMarkupFactor = 1;

    /**
     * Drop rate percentage, for products without purchase price
     * @var integer
     */
    protected $dropRate = 10;

    /**
     * Drop factor on price for products without purchase price
     * @var float
     */
    protected $dropRateFactor = 0.9;

    /**
     * fee factor if fees are used on selling price
     * @var float
     */
    protected $feeFactor = 1;

    /**
     * Gap with competitor in euro when we set a lower price
     * @var float
     */
    protected $competitorGap = 0.01;

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
     * set fee selling rate
     * @param float $fee       Fee percentage
     * @return self
     */
    public function setFeeRate(int $fee) : self
    {
        $this->feeRate = $fee;
        $this->feeFactor = $this->getMarkupFactor($fee);

        return $this;
    }

    /**
     * Get fee selling rate
     * @return int
     */
    public function getFeeRate() : int
    {
        return $this->feeRate;
    }

    /**
     * Get fee factor
     * @return float
     */
    protected function getFeeFactor() : float
    {
        return $this->feeFactor;
    }

    /**
     * Optional config, default is 0.01
     * @param float $amount Euros
     * @return self
     */
    public function setCompetitorGap(float $amount) : self
    {
        $this->competitorGap = $amount;

        return $this;
    }

    /**
     * Gap between competitve price and pricer output
     * @return float
     */
    public function getCompetitorGap() : float
    {
        return $this->competitorGap;
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
     * Get shipping scale array
     * @return array
     */
    public function getShippingScale() : array
    {
        return $this->shippingScale;
    }

    /**
     * Enable/Disable fees on shipping cost
     * @param boolean $feeOnShipping
     * @return self
     */
    public function setFeeOnShipping(bool $feeOnShipping) : self
    {
        $this->feeOnShipping = $feeOnShipping;

        return $this;
    }

    /**
     * Get status for fees on shipping cost
     * @return bool
     */
    public function getFeeOnShipping() : bool
    {
        return $this->feeOnShipping;
    }

    /**
     * Set competitor policy
     * @param int $policy Pricer::ALIGN | Pricer::NO_ALIGN
     * @return self
     */
    public function setCompetitorPolicy(int $policy) : self
    {
        $this->competitorPolicy = $policy;

        return $this;
    }

    /**
     * Get competitor policy
     * @return int
     */
    public function getCompetitorPolicy() : int
    {
        return $this->competitorPolicy;
    }

    /**
     * Set no competitor policy
     * @param int $policy   Pricer::BASE_PRICE | Pricer::TARGET_BELOW_BASE_PRICE | Pricer::TARGET_PRICE
     */
    public function setNoCompetitorPolicy(int $policy) : self
    {
        $this->noCompetitorPolicy = $policy;

        return $this;
    }

    /**
     * Get no competitor policy
     * @return int
     */
    public function getNoCompetitorPolicy() : int
    {
        return $this->noCompetitorPolicy;
    }

    /**
     * Set desired selling markup if no competitors
     * @param int $targetMarkup Selling markup in percentage
     * @return self
     */
    public function setTargetMarkup(int $targetMarkup) : self
    {
        $this->targetMarkup = $targetMarkup;
        $this->targetMarkupFactor = $this->getMarkupFactor($targetMarkup);

        return $this;
    }

    /**
     * Get desired selling markup if no competitors
     * @return int
     */
    public function getTargetMarkup() : int
    {
        return $this->targetMarkup;
    }

    /**
     * Get target factor used to compute target price from the purchase price
     * @return float
     */
    protected function getTargetMarkupFactor() : float
    {
        return $this->targetMarkupFactor;
    }

    /**
     * Set minimal selling markup if competitors
     * @param int $alignMarkup margin rate %
     * @return self
     */
    public function setAlignMarkup(int $alignMarkup = null) : self
    {
        $this->alignMarkup = $alignMarkup;
        if (!isset($alignMarkup)) {
            $this->alignMarkupFactor = null;

            return $this;
        }
        $this->alignMarkupFactor = $this->getMarkupFactor($alignMarkup);

        return $this;
    }

    /**
     * Get align markup, selling markup percentage
     * @return int
     */
    public function getAlignMarkup() : int
    {
        return $this->alignMarkup;
    }

    /**
     * Get minimal align factor used to compute minimal price from purchase price
     * @return float
     */
    protected function getAlignMarkupFactor() : float
    {
        return $this->alignMarkupFactor;
    }

    /**
     * Allowed drop rate on price for products without purchase price
     * @param int $rate
     * @return self
     */
    public function setDropRate(int $rate) : self
    {
        $this->dropRate = $rate;
        $this->dropRateFactor = (100-$rate)/100;

        return $this;
    }

    /**
     * Get allowed drop rate on price for products without purchase price
     * @return int
     */
    public function getDropRate() : int
    {
        return $this->dropRate;
    }

    /**
     * Allowed drop factor if no purchase price
     * @return float
     */
    protected function getDropFactor() : float
    {
        return $this->dropRateFactor;
    }

    /**
     * Get minimal selling price
     * @param float $purchasePrice
     * @return float
     */
    protected function getMinPrice(float $purchasePrice) : float
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
    protected function getTargetPrice(float $purchasePrice) : float
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
     * @throws \Exception
     * @param float $sellingPrice   A selling price with all fees included except shipping
     */
    protected function getShippingPrice(float $sellingPrice) : float
    {
        if (count($this->shippingScale) > 0 && 0 === (int) round(100 * $this->getShippingCost())) {
            throw new \Exception('Shipping cost is required with a shipping scale');
        }

        foreach ($this->shippingScale as $scale) {
            $shipping =
                (($this->getShippingCost() * $this->getFeeFactor()) - $scale[1])
                / $this->getFeeFactor();

            if ($this->feeOnShipping) {
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
     * @param CompetitorPrice $competitorPrice
     * @param float $minPrice
     *
     * @return bool
     */
    protected function canUseCompetitor(CompetitorPrice $competitorPrice, float $minPrice) : bool
    {
        $estimatedCents = (int) round(100 * ($competitorPrice->sellingPrice - $this->getCompetitorGap()));

        return ($estimatedCents >= (int) round(100 * $minPrice));
    }

    /**
     * Get price with competitor
     * @param float $basePrice
     * @param CompetitorPrice $competitorPrice
     * @param float $targetPrice
     * @param float $minPrice
     * @param float $purchasePrice
     * @return WiningPrice
     */
    protected function getPriceWithCompetitor(float $basePrice, CompetitorPrice $competitorPrice, float $targetPrice = null, float $minPrice, float $purchasePrice = null)
    {
        $price = new WiningPrice();
        $price->sellingPrice = $basePrice;
        $price->type = WiningPrice::BASE;
        $price->competitorPrice = $competitorPrice;

        if (isset($targetPrice) && $competitorPrice->sellingPrice > $targetPrice) {
            $price->setSellingPriceDown($targetPrice, WiningPrice::TARGET);
            return $price;
        }

        if (!isset($this->alignMarkupFactor)) {
            return $price;
        }

        if ($this->canUseCompetitor($competitorPrice, $minPrice)) {
            $price->setSellingPriceDown(
                round($competitorPrice->sellingPrice - $this->competitorGap, 2),
                WiningPrice::COMPETITOR
            );
            return $price;
        }

        $price->setSellingPriceDown(
            round($minPrice, 2),
            isset($purchasePrice) ? WiningPrice::MIN : WiningPrice::MIN_RATED
        );
        return $price;
    }

    /**
     * Get price with no competitor
     * @param float $basePrice
     * @param float $targetPrice
     * @return WiningPrice
     */
    protected function getPriceWithNoCompetitor(float $basePrice, float $targetPrice = null) : WiningPrice
    {
        $price = new WiningPrice();
        $price->sellingPrice = $basePrice;
        $price->type = WiningPrice::BASE;


        if (!isset($targetPrice)) {
            return $price;
        }

        if (Pricer::TARGET_BELOW_BASE_PRICE === $this->noCompetitorPolicy) {
            // If the price remain higher than target price, normalize
            $price->setSellingPriceDown($targetPrice, WiningPrice::TARGET);
        }

        if (Pricer::TARGET_PRICE === $this->noCompetitorPolicy) {
            $price->type = WiningPrice::TARGET;
            $price->sellingPrice = $targetPrice;
        }

        return $price;
    }


    /**
     * Compute a wining price
     *
     * @param float         $basePrice              Store 0 price, never modified by the pricer
     * @param float         $purchasePrice          Can be null
     * @param CompetitorPrice    $competitor             Can be null
     *
     * @return WiningPrice
     */
    public function getWiningPrice(float $basePrice, float $purchasePrice = null, CompetitorPrice $competitorPrice = null) : WiningPrice
    {
        $price = new WiningPrice();
        $price->sellingPrice = $basePrice;
        $price->type = WiningPrice::BASE;

        $targetPrice = null;
        $minPrice = $basePrice * $this->dropRateFactor;

        if (isset($purchasePrice)) {
            $targetPrice = $this->getTargetPrice($purchasePrice);
            $minPrice = $this->getMinPrice($purchasePrice);
        }

        if (isset($competitorPrice)) {
            return $this->getPriceWithCompetitor($basePrice, $competitorPrice, $targetPrice, $minPrice, $purchasePrice);
        }

        return $this->getPriceWithNoCompetitor($basePrice, $targetPrice);
    }
}
