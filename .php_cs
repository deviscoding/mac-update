<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$header = <<<'EOF'
This file is part of PHP CS Fixer.
(c) Fabien Potencier <fabien@symfony.com>
    Dariusz Rumiński <dariusz.ruminski@gmail.com>
This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

return PhpCsFixer\Config::create()
   ->setRules([
        '@Symfony' => true,
        'phpdoc_no_package' => false,
        'binary_operator_spaces' => [ 'default' => 'align_single_space_minimal'],
         'braces' => [ 'position_after_control_structures' => 'next' ]
    ])
   ->setIndent("  ");