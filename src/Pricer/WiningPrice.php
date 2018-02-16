<?php
namespace Pricer;

/**
 * Represent a new price on a product
 */
class WiningPrice
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
    public $sellingPrice;


    /**
     * Price type
     * @var string
     */
    public $type;

    /**
     * If margin lower than targetMargin, contain the competitor
     * @var Competitor
     */
    public $competitor = null;

    /**
     * Error on price, can be null
     * @var UnexpectedPriceException
     */
    public $error = null;

    /**
     * Set the selling price only if the new value is lower than current value
     * @param float $sellingPrice
     * @param WiningPrice::BASE | WiningPrice::COMPETITOR | WiningPrice::MIN | WiningPrice::TARGET
     * @return bool
     */
    public function setSellingPriceDown(float $sellingPrice, string $type) : bool
    {
        if ($this->sellingPrice < $sellingPrice) {
            return false;
        }

        $this->sellingPrice = $sellingPrice;
        $this->type = $type;
        return true;
    }


    public function getCents() : int
    {
        return (int) round(100 * $this->sellingPrice);
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
