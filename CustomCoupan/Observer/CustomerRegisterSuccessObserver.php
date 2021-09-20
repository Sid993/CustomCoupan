<?php

namespace Test\CustomCoupan\Observer;

use Magento\Framework\Event\ObserverInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\Data\CouponInterface;
use Magento\Framework\Exception\InputException;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;

/**
 * Observer class.
 */
class CustomerRegisterSuccessObserver implements ObserverInterface
{
     /**
     * @var LoggerInterface
     */
    private $logger;
 
    /**
     * @var CouponRepositoryInterface
     */
    protected $couponRepository;
 
    /**
     * @var RuleRepositoryInterface
     */
    protected $ruleRepository;
 
    /**
     * @var Rule
     */
    protected $rule;
 
    /**
     * @var CouponInterface
     */
    protected $coupon;
 
    public function __construct(
        CouponRepositoryInterface $couponRepository,
        RuleRepositoryInterface $ruleRepository,
        \Magento\SalesRule\Helper\Coupon $salesRuleCoupon,
        RuleInterfaceFactory $rule,
        CouponInterface $coupon,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->couponRepository = $couponRepository;
        $this->salesRuleCoupon = $salesRuleCoupon;
        $this->ruleRepository = $ruleRepository;
        $this->rule = $rule;
        $this->coupon = $coupon;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $customer = $observer->getCustomer();
        $websiteId = [];
        $websiteId [] = $customer->getWebsiteId();
        $price = 20;
        $newRule = $this->rule->create();
        $newRule->setName('New Customer Discount')
            ->setDescription("20% Discount for New Customer Registration")
            ->setIsAdvanced(true)
            ->setStopRulesProcessing(false)
            ->setDiscountQty(1)
            ->setCustomerGroupIds([0, 1, 2])
            ->setWebsiteIds($websiteId)
            ->setIsRss(1)
            ->setUsesPerCoupon(1)
            ->setDiscountStep(0)
            ->setCouponType(RuleInterface::COUPON_TYPE_SPECIFIC_COUPON)
            ->setSimpleAction(RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART)
            ->setDiscountAmount($price)
            ->setIsActive(true);
 
        try {
            $ruleCreate = $this->ruleRepository->save($newRule);
            if ($ruleCreate->getRuleId()) {
                $coupan = $this->createCoupon($ruleCreate->getRuleId());
            }
            $this->messageManager->addSuccessMessage(
                __('You have get a coupan code %1 of price %2', $coupan['coupan'], $price)
            );
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * Create Coupon by Rule id.
     *
     * @param int $ruleId
     *
     * @return int|null
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createCoupon(int $ruleId) {
        /** @var CouponInterface $coupon */
        $coupanCode = $this->generateCode();
        $coupon = $this->coupon;
        $coupon->setCode($coupanCode)
            ->setIsPrimary(1)
            ->setRuleId($ruleId);
 
        /** @var CouponRepositoryInterface $couponRepository */
        $coupon = $this->couponRepository->save($coupon);

        $coupan = [
            'coupan' => $coupanCode,
            'coupan_id' => $coupon->getCouponId()
        ];
        return $coupan;
    }

    /**
     * Generate coupon code
     *
     * @return string
     */
    public function generateCode()
    {
        $format = \Magento\SalesRule\Helper\Coupon::COUPON_FORMAT_ALPHANUMERIC;

        $splitChar = $this->getDelimiter();
        $charset = $this->salesRuleCoupon->getCharset($format);

        $code = '';
        $charsetSize = count($charset);
        $split = 0;
        $length = 15;
        for ($i = 0; $i < $length; ++$i) {
            $char = $charset[\Magento\Framework\Math\Random::getRandomNumber(0, $charsetSize - 1)];
            if (($split > 0) && (($i % $split) === 0) && ($i !== 0)) {
                $char = $splitChar . $char;
            }
            $code .= $char;
        }

        return $code;
    }
    
    /**
     * Retrieve delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->salesRuleCoupon->getCodeSeparator();
    }
}
