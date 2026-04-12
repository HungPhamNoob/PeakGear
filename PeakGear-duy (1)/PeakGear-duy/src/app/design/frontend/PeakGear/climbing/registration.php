<?php
/**
 * PeakGear Climbing Theme
 * 
 * @category  PeakGear
 * @package   PeakGear_Climbing
 * @author    PeakGear Team
 * @copyright Copyright (c) 2026 PeakGear
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/PeakGear/climbing',
    __DIR__
);
