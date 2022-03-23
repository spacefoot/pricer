<?php

namespace Pricer;

/**
 * Generic pricer without database connexion.
 */
class Pricer
{
    /**
     * competitor policy, do not align to competitor price.
     *
     * @var int
     */
    const NO_ALIGN = 0;

    /**
     * competitor policy, align to competitor price.
     *
     * @var int
     */
    const ALIGN = 1;

    /**
     * competitor policy, always align to competitor price, even if target price if below.
     *
     * @var int
     */
    const ALIGN_ALWAYS = 2;

    /**
     * No competitor policy, return base price.
     *
     * @var int
     */
    const BASE_PRICE = 0;

    /**
     * No competitor policy, always decrease to target price if price is higher than target selling markup.
     *
     * @var int
     */
    const TARGET_BELOW_BASE_PRICE = 1;

    /**
     * No competitor policy, always change price to target price.
     *
     * @var int
     */
    const TARGET_PRICE = 2;

    /**
     * Real cost of shipping for the seller.
     *
     * @var float
     */
    protected $shippingCost = 0.0;

    /**
     * Shipping scale used to ccompute shipping cost.
     *
     * @var array
     */
    protected $shippingScale = [];

    /**
     * Use fee on shipping cost.
     *
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
     * Align selling markup percentage, can be null.
     *
     * @var float
     */
    protected $alignMarkup = null;

    /**
     * factor to obtain the minimal selling price from purchase price
     * if null, disable alignement to competitor price.
     *
     * @var float
     */
    protected $alignMarkupFactor = null;

    /**
     * Minimum markup percentage, can be null.
     *
     * @var float
     */
    protected $minMarkup = null;

    /**
     * factor to obtain the minimal selling price from purchase price
     *
     * @var float
     */
    protected $minMarkupFactor = null;

    /**
     * Raise base price if below align markup
     *
     * @var bool
     */
    protected $raiseBasePriceIfBelowMinMarkup = false;

    /**
     * target selling markup percentage.
     *
     * @var float
     */
    protected $targetMarkup = null;

    /**
     * factor to obtain the desired selling price from purchase price.
     *
     * @var float
     */
    protected $targetMarkupFactor = null;

    /**
     * Drop rate percentage, for products without purchase price.
     *
     * @var float
     */
    protected $dropRate = 10;

    /**
     * Drop factor on price for products without purchase price.
     *
     * @var float
     */
    protected $dropRateFactor = 0.9;

    /**
     * Fee rate.
     *
     * @var float
     */
    protected $feeRate = 0.0;

    /**
     * fee factor if fees are used on selling price.
     *
     * @var float
     */
    protected $feeFactor = 1;

    /**
     * Gap with competitor in euro when we set a lower price.
     *
     * @var float
     */
    protected $competitorGap = 0.01;

    /**
     * Get factor from selling markup (taux de marque)
     * to apply on purchase price.
     *
     * @param float $rate
     *
     * @return float
     */
    private function getRateFactor(float $rate): float
    {
        return 100 / (100 - $rate);
    }

    /**
     * set fee selling rate.
     *
     * @param float $fee Fee percentage
     *
     * @return self
     */
    public function setFeeRate(float $fee): self
    {
        $this->feeRate = $fee;
        $this->feeFactor = $this->getRateFactor($fee);

        return $this;
    }

    /**
     * Get fee selling rate.
     *
     * @return float
     */
    public function getFeeRate(): float
    {
        return $this->feeRate;
    }

    /**
     * Get fee factor.
     *
     * @return float
     */
    protected function getFeeFactor(): float
    {
        return $this->feeFactor;
    }

    /**
     * Optional config, default is 0.01.
     *
     * @param float $amount Euros
     *
     * @return self
     */
    public function setCompetitorGap(float $amount): self
    {
        $this->competitorGap = $amount;

        return $this;
    }

    /**
     * Gap between competitve price and pricer output.
     *
     * @return float
     */
    public function getCompetitorGap(): float
    {
        return $this->competitorGap;
    }

    /**
     * Set shipping object used to compute shipping cost
     * First value is the upper limit to compare with total selling price
     * Second value is the amount of shipping.
     *
     * @param array $shippingScale
     *
     * @return self
     */
    public function setShippingScale(array $shippingScale): self
    {
        $this->shippingScale = $shippingScale;

        return $this;
    }

    /**
     * Get shipping scale array.
     *
     * @return array
     */
    public function getShippingScale(): array
    {
        return $this->shippingScale;
    }

    /**
     * Enable/Disable fees on shipping cost.
     *
     * @param bool $feeOnShipping
     *
     * @return self
     */
    public function setFeeOnShipping(bool $feeOnShipping): self
    {
        $this->feeOnShipping = $feeOnShipping;

        return $this;
    }

    /**
     * Get status for fees on shipping cost.
     *
     * @return bool
     */
    public function getFeeOnShipping(): bool
    {
        return $this->feeOnShipping;
    }

    /**
     * Set competitor policy.
     *
     * @param int $policy Pricer::ALIGN | Pricer::NO_ALIGN
     *
     * @return self
     */
    public function setCompetitorPolicy(int $policy): self
    {
        $this->competitorPolicy = $policy;

        return $this;
    }

    /**
     * Get competitor policy.
     *
     * @return int
     */
    public function getCompetitorPolicy(): int
    {
        return $this->competitorPolicy;
    }

    /**
     * Set no competitor policy.
     *
     * @param int $policy Pricer::BASE_PRICE | Pricer::TARGET_BELOW_BASE_PRICE | Pricer::TARGET_PRICE
     */
    public function setNoCompetitorPolicy(int $policy): self
    {
        $this->noCompetitorPolicy = $policy;

        return $this;
    }

    /**
     * Get no competitor policy.
     *
     * @return int
     */
    public function getNoCompetitorPolicy(): int
    {
        return $this->noCompetitorPolicy;
    }

    /**
     * Set desired selling markup.
     *
     * @param int $targetMarkup Selling markup in percentage
     *
     * @return self
     */
    public function setTargetMarkup(float $targetMarkup = null): self
    {
        $this->targetMarkup = $targetMarkup;
        $this->targetMarkupFactor = null;
        if (isset($targetMarkup)) {
            $this->targetMarkupFactor = $this->getRateFactor($targetMarkup);
        }

        return $this;
    }

    /**
     * Get desired selling markup.
     *
     * @return float
     */
    public function getTargetMarkup(): float
    {
        return $this->targetMarkup;
    }

    /**
     * Get target factor used to compute target price from the purchase price.
     *
     * @return float
     */
    protected function getTargetMarkupFactor(): float
    {
        return $this->targetMarkupFactor;
    }

    /**
     * Set minimal selling markup if competitors.
     *
     * @param int $alignMarkup margin rate %
     *
     * @return self
     */
    public function setAlignMarkup(float $alignMarkup = null): self
    {
        $this->alignMarkup = $alignMarkup;
        if (!isset($alignMarkup)) {
            $this->alignMarkupFactor = null;

            return $this;
        }
        $this->alignMarkupFactor = $this->getRateFactor($alignMarkup);

        return $this;
    }

    /**
     * Set minimum markup
     *
     * @param float $minMarkup margin rate %
     *
     * @return self
     */
    public function setMinMarkup(float $minMarkup = null): self
    {
        $this->minMarkup = $minMarkup;
        if (!isset($minMarkup)) {
            $this->minMarkupFactor = null;

            return $this;
        }
        $this->minMarkupFactor = $this->getRateFactor($minMarkup);

        return $this;
    }

    /**
     * Set mode to raise base price when below min markup
     *
     * @param bool raiseBasePriceIfBelowMinMarkup
     *
     * @return self
     */
    public function setRaiseBasePriceIfBelowMinMarkup(bool $raiseBasePriceIfBelowMinMarkup): self
    {
        $this->raiseBasePriceIfBelowMinMarkup = $raiseBasePriceIfBelowMinMarkup;

        return $this;
    }

    /**
     * Get align markup, selling markup percentage.
     *
     * @return float
     */
    public function getAlignMarkup(): float
    {
        return $this->alignMarkup;
    }

    /**
     * Get min markup.
     *
     * @return float
     */
    public function getMinMarkup(): float
    {
        return $this->minMarkup;
    }


    /**
     * Return true if base price should be raised when below min markup
     *
     * @return bool
     */
    public function getRaiseBasePriceIfBelowMinMarkup(): bool
    {
        return $this->raiseBasePriceIfBelowMinMarkup;
    }

    /**
     * Get minimal align factor used to compute minimal price from purchase price.
     *
     * @return float
     */
    protected function getAlignMarkupFactor(): float
    {
        return $this->alignMarkupFactor;
    }

    /**
     * Allowed drop rate on price for products without purchase price.
     *
     * @param float $rate
     *
     * @return self
     */
    public function setDropRate(float $rate): self
    {
        $this->dropRate = $rate;
        $this->dropRateFactor = (100 - $rate) / 100;

        return $this;
    }

    /**
     * Get allowed drop rate on price for products without purchase price.
     *
     * @return int
     */
    public function getDropRate(): float
    {
        return $this->dropRate;
    }

    /**
     * Allowed drop factor if no purchase price.
     *
     * @return float
     */
    protected function getDropFactor(): float
    {
        return $this->dropRateFactor;
    }

    /**
     * Get minimal selling price.
     *
     * @param float $purchasePrice
     *
     * @return float
     */
    protected function getMinPrice(float $purchasePrice): float
    {
        if (!isset($this->alignMarkupFactor)) {
            return $this->getTargetPrice($purchasePrice);
        }

        $minPrice = $purchasePrice * $this->alignMarkupFactor * $this->feeFactor;
        $minPrice += $this->getShippingPrice($minPrice);

        return round($minPrice, 2);
    }

    /**
     * Get target selling price.
     *
     * @param float $purchasePrice
     *
     * @return float
     */
    protected function getTargetPrice(float $purchasePrice): float
    {
        if (!isset($this->targetMarkupFactor)) {
            throw new \Exception('A target markup is required');
        }

        $targetPrice = $purchasePrice * $this->targetMarkupFactor * $this->feeFactor;
        $targetPrice += $this->getShippingPrice($targetPrice);

        return round($targetPrice, 2);
    }

    /**
     * Get target selling price.
     *
     * @param float $purchasePrice
     *
     * @return float
     */
    protected function getRaisedBasePrice(float $purchasePrice): float
    {
        if (!isset($this->minMarkupFactor)) {
            throw new \Exception('A min markup is required');
        }

        $raisedBasePrice = $purchasePrice * $this->minMarkupFactor * $this->feeFactor;
        $raisedBasePrice += $this->getShippingPrice($raisedBasePrice);

        return round($raisedBasePrice, 2);
    }

    /**
     * Set the real shipping cost paid by seller.
     *
     * @param float $shippingCost
     *
     * @return self
     */
    public function setShippingCost(float $shippingCost): self
    {
        $this->shippingCost = $shippingCost;

        return $this;
    }

    /**
     * Get the real shipping cost paid by seller.
     *
     * @return float
     */
    public function getShippingCost(): float
    {
        return $this->shippingCost;
    }

    /**
     * Get computed shipping price.
     *
     * @throws \Exception
     *
     * @param float $sellingPrice A selling price with all fees included except shipping
     */
    protected function getShippingPrice(float $sellingPrice): float
    {
        if (count($this->shippingScale) > 0 && 0 === (int) round(100 * $this->getShippingCost())) {
            throw new \Exception('Shipping cost is required with a shipping scale');
        }

        foreach ($this->shippingScale as $scale) {
            $shipping = ($this->getShippingCost() * $this->getFeeFactor()) - $scale[1];

            if (!$this->feeOnShipping) {
                $shipping /= $this->getFeeFactor();
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
     * Can use competitor if competitor price - 0.01 >= min price.
     *
     * @param float $competitorPrice
     * @param float $minPrice
     *
     * @return bool
     */
    protected function canUseCompetitor(float $competitorPrice, float $minPrice): bool
    {
        $estimatedCents = (int) round(100 * ($competitorPrice - $this->getCompetitorGap()));

        return $estimatedCents >= (int) round(100 * $minPrice);
    }

    /**
     * Get price with competitor.
     *
     * @param float $basePrice
     * @param float $competitorPrice
     * @param float $targetPrice
     * @param float $minPrice
     * @param float $purchasePrice
     *
     * @return WinningPrice
     */
    protected function getPriceWithCompetitor(float $basePrice, float $competitorPrice, float $targetPrice = null, float $minPrice, float $purchasePrice = null)
    {
        $price = new WinningPrice();
        $price->value = $basePrice;
        $price->type = WinningPrice::BASE;

        if (self::NO_ALIGN === $this->competitorPolicy) {
            if (isset($targetPrice)) {
                $price->setSellingPriceDown($targetPrice, WinningPrice::TARGET);
            }

            return $price;
        }

        if (self::ALIGN === $this->competitorPolicy && isset($targetPrice) && $competitorPrice > $targetPrice) {
            $price->setSellingPriceDown($targetPrice, WinningPrice::TARGET);

            return $price;
        }

        if (!isset($this->alignMarkupFactor)) {
            return $price;
        }

        if ($this->canUseCompetitor($competitorPrice, $minPrice)) {
            $price->setSellingPriceDown(
                round($competitorPrice - $this->competitorGap, 2),
                self::ALIGN_ALWAYS === $this->competitorPolicy && $competitorPrice > $targetPrice ? WinningPrice::COMPETITOR_ALWAYS : WinningPrice::COMPETITOR
            );

            return $price;
        }

        $price->setSellingPriceDown(
            round($minPrice, 2),
            isset($purchasePrice) ? WinningPrice::MIN : WinningPrice::MIN_RATED
        );

        return $price;
    }

    /**
     * Get price with no competitor.
     *
     * @param float $basePrice
     * @param float $targetPrice
     *
     * @return WinningPrice
     */
    protected function getPriceWithNoCompetitor(float $basePrice, float $targetPrice = null): WinningPrice
    {
        $price = new WinningPrice();
        $price->value = $basePrice;
        $price->type = WinningPrice::BASE;

        if (!isset($targetPrice)) {
            return $price;
        }

        if (self::TARGET_BELOW_BASE_PRICE === $this->noCompetitorPolicy) {
            // If the price remain higher than target price, normalize
            $price->setSellingPriceDown($targetPrice, WinningPrice::TARGET);
        }

        if (self::TARGET_PRICE === $this->noCompetitorPolicy) {
            $price->type = WinningPrice::TARGET;
            $price->value = $targetPrice;
        }

        return $price;
    }

    /**
     * Compute a winning price.
     *
     * @param float $basePrice       base price, never modified by the pricer
     * @param float $purchasePrice   Can be null
     * @param float $competitorPrice Can be null
     *
     * @return WinningPrice
     */
    public function getWinningPrice(float $basePrice, float $purchasePrice = null, float $competitorPrice = null): WinningPrice
    {
        $targetPrice = null;
        $minPrice = $basePrice * $this->dropRateFactor;

        if (isset($purchasePrice)) {
            $targetPrice = $this->getTargetPrice($purchasePrice);
            $minPrice = $this->getMinPrice($purchasePrice);
        }

        if (isset($competitorPrice)) {
            $winningPrice = $this->getPriceWithCompetitor($basePrice, $competitorPrice, $targetPrice, $minPrice, $purchasePrice);
        } else {
            $winningPrice = $this->getPriceWithNoCompetitor($basePrice, $targetPrice);
        }

        // Raise base price if below min markup (optional)
        if ($winningPrice->type === WinningPrice::BASE
            && $this->raiseBasePriceIfBelowMinMarkup
            && $purchasePrice !== null) {

            $raisedBasePrice = $this->getRaisedBasePrice($purchasePrice);
            $winningPrice->setSellingPriceUp($raisedBasePrice, WinningPrice::BASE_RAISED);
        }

        return $winningPrice;
    }
}
