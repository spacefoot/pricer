<?php
namespace Pricer;

class CompetitorPrice
{
    /**
     * Competitor selling price, including taxes
     * @var float
     */
    public $sellingPrice;

    /**
     * Competitor
     * Website where the competitor has been found
     * @var string
     */
    public $name;


    public function __construct(float $price)
    {
        $this->sellingPrice = $price;
    }
}
