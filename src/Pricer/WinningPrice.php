<?php
namespace Pricer;

/**
 * Represent a new price on a product
 */
class WinningPrice
{
    const BASE          = 'Unmodified base price';
    const COMPETITOR    = 'Aligned to competitor';
    const MIN           = 'Limited by align markup';
    const TARGET        = 'Limited by target markup';
    const MIN_RATED     = 'Max price drop from base price';


    /**
     * Selling price
     * @var float
     */
    public $value;


    /**
     * Price type
     * @var string
     */
    public $type;


    /**
     * Set the selling price only if the new value is lower than current value
     * @param float $price
     * @param WinningPrice::BASE | WinningPrice::COMPETITOR | WinningPrice::MIN | WinningPrice::TARGET
     * @return bool
     */
    public function setSellingPriceDown(float $price, string $type) : bool
    {
        if ($this->value < $price) {
            return false;
        }

        $this->value = $price;
        $this->type = $type;
        return true;
    }


    public function getCents() : int
    {
        return (int) round(100 * $this->value);
    }

    /**
     * Test if price from databse is equal
     * @param string | null $price
     * @return bool
     */
    public function isEqual($price) : bool
    {
        if (null === $price) {
            return false;
        }

        $priceCentimes = (int) round(100 * (float) $price);
        return ($this->getCents() === $priceCentimes);
    }
}
