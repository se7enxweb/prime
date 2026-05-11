<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Locale;

class LocaleTest extends AbstractLocaleTest
{
    /**
     */
    public function testAcceptFromHttp()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('acceptFromHttp', 'pt-br,en-us;q=0.7,en;q=0.5');
    }

    /**
     */
    public function testComposeLocale()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $subtags = array(
            'language' => 'pt',
            'script' => 'Latn',
            'region' => 'BR',
        );
        $this->call('composeLocale', $subtags);
    }

    /**
     */
    public function testFilterMatches()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('filterMatches', 'pt-BR', 'pt-BR');
    }

    /**
     */
    public function testGetAllVariants()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getAllVariants', 'pt_BR_Latn');
    }

    /**
     */
    public function testGetDisplayLanguage()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getDisplayLanguage', 'pt-Latn-BR', 'en');
    }

    /**
     */
    public function testGetDisplayName()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getDisplayName', 'pt-Latn-BR', 'en');
    }

    /**
     */
    public function testGetDisplayRegion()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getDisplayRegion', 'pt-Latn-BR', 'en');
    }

    /**
     */
    public function testGetDisplayScript()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getDisplayScript', 'pt-Latn-BR', 'en');
    }

    /**
     */
    public function testGetDisplayVariant()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getDisplayVariant', 'pt-Latn-BR', 'en');
    }

    /**
     */
    public function testGetKeywords()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getKeywords', 'pt-BR@currency=BRL');
    }

    /**
     */
    public function testGetPrimaryLanguage()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getPrimaryLanguage', 'pt-Latn-BR');
    }

    /**
     */
    public function testGetRegion()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getRegion', 'pt-Latn-BR');
    }

    /**
     */
    public function testGetScript()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('getScript', 'pt-Latn-BR');
    }

    /**
     */
    public function testLookup()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $langtag = array(
            'pt-Latn-BR',
            'pt-BR',
        );
        $this->call('lookup', $langtag, 'pt-BR-x-priv1');
    }

    /**
     */
    public function testParseLocale()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('parseLocale', 'pt-Latn-BR');
    }

    /**
     */
    public function testSetDefault()
    {
        $this->expectException(\Symfony\Component\Intl\Exception\MethodNotImplementedException::class);

        $this->call('setDefault', 'pt_BR');
    }

    public function testSetDefaultAcceptsEn()
    {
        $this->call('setDefault', 'en');

        $this->assertSame('en', $this->call('getDefault'));
    }

    protected function call($methodName)
    {
        $args = \array_slice(\func_get_args(), 1);

        return \call_user_func_array(array('Symfony\Component\Intl\Locale\Locale', $methodName), $args);
    }
}
