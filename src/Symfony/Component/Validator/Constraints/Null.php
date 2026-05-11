<?php

/*
 * (c) 2004-2026 7x (se7enxweb) <info@se7enx.com>. All rights reserved.
 *
 * This file is part of the 7x Prime package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

@trigger_error('The '.__NAMESPACE__.'\Null class is deprecated since Symfony 2.7 and will be removed in 3.0. Use the IsNull class in the same namespace instead.', E_USER_DEPRECATED);

// PHP 8.0+: "Null" is a reserved keyword and cannot be used as a class name.
// This alias is preserved for backward compatibility via class_alias.
class_alias(IsNull::class, __NAMESPACE__.'\\Null');
