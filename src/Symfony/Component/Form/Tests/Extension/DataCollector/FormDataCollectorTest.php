<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormView;

class FormDataCollectorTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $dataExtractor;

    /**
     * @var FormDataCollector
     */
    private $dataCollector;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $dispatcher;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $factory;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $dataMapper;

    /**
     * @var Form
     */
    private $form;

    /**
     * @var Form
     */
    private $childForm;

    /**
     * @var FormView
     */
    private $view;

    /**
     * @var FormView
     */
    private $childView;

    protected function setUp(): void
    {
        $this->dataExtractor = $this->getMockBuilder('Symfony\Component\Form\Extension\DataCollector\FormDataExtractorInterface')->getMock();
        $this->dataCollector = new FormDataCollector($this->dataExtractor);
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
        $this->dataMapper = $this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock();
        $this->form = $this->createForm('name');
        $this->childForm = $this->createForm('child');
        $this->view = new FormView();
        $this->childView = new FormView();
    }

    public function testBuildPreliminaryFormTree()
    {
        $this->form->add($this->childForm);

        $form = $this->form;
        $childForm = $this->childForm;
        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('config' => 'foo');
                if ($f === $childForm) return array('config' => 'bar');
                return array();
            });
        $this->dataExtractor->expects($this->any())
            ->method('extractDefaultData')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('default_data' => 'foo');
                if ($f === $childForm) return array('default_data' => 'bar');
                return array();
            });
        $this->dataExtractor->expects($this->any())
            ->method('extractSubmittedData')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('submitted_data' => 'foo');
                if ($f === $childForm) return array('submitted_data' => 'bar');
                return array();
            });

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $childFormData = array(
             'config' => 'bar',
             'default_data' => 'bar',
             'submitted_data' => 'bar',
             'children' => array(),
         );

        $formData = array(
             'config' => 'foo',
             'default_data' => 'foo',
             'submitted_data' => 'foo',
             'children' => array(
                 'child' => $childFormData,
             ),
         );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'nb_errors' => 0,
         ), $this->dataCollector->getData());
    }

    public function testBuildMultiplePreliminaryFormTrees()
    {
        $form1 = $this->createForm('form1');
        $form2 = $this->createForm('form2');

        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->willReturnCallback(function ($f) use ($form1, $form2) {
                if ($f === $form1) return array('config' => 'foo');
                if ($f === $form2) return array('config' => 'bar');
                return array();
            });

        $this->dataCollector->collectConfiguration($form1);
        $this->dataCollector->collectConfiguration($form2);
        $this->dataCollector->buildPreliminaryFormTree($form1);

        $form1Data = array(
            'config' => 'foo',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'form1' => $form1Data,
            ),
            'forms_by_hash' => array(
                spl_object_hash($form1) => $form1Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->buildPreliminaryFormTree($form2);

        $form2Data = array(
            'config' => 'bar',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'form1' => $form1Data,
                'form2' => $form2Data,
            ),
            'forms_by_hash' => array(
                spl_object_hash($form1) => $form1Data,
                spl_object_hash($form2) => $form2Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildSamePreliminaryFormTreeMultipleTimes()
    {
        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->with($this->form)
            ->willReturn(array('config' => 'foo'));

        $this->dataExtractor->expects($this->any())
            ->method('extractDefaultData')
            ->with($this->form)
->willReturn(array('default_data' => 'foo'));

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = array(
            'config' => 'foo',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = array(
            'config' => 'foo',
            'default_data' => 'foo',
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildPreliminaryFormTreeWithoutCollectingAnyData()
    {
        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $formData = array(
            'children' => array(),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testBuildFinalFormTree()
    {
        $this->form->add($this->childForm);
        $this->view->children['child'] = $this->childView;

        $form = $this->form;
        $childForm = $this->childForm;
        $view = $this->view;
        $childView = $this->childView;
        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('config' => 'foo');
                if ($f === $childForm) return array('config' => 'bar');
                return array();
            });
        $this->dataExtractor->expects($this->any())
            ->method('extractDefaultData')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('default_data' => 'foo');
                if ($f === $childForm) return array('default_data' => 'bar');
                return array();
            });
        $this->dataExtractor->expects($this->any())
            ->method('extractSubmittedData')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('submitted_data' => 'foo');
                if ($f === $childForm) return array('submitted_data' => 'bar');
                return array();
            });
        $this->dataExtractor->expects($this->any())
            ->method('extractViewVariables')
            ->willReturnCallback(function ($v) use ($view, $childView) {
                if ($v === $view) return array('view_vars' => 'foo');
                if ($v === $childView) return array('view_vars' => 'bar');
                return array();
            });

        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectDefaultData($this->form);
        $this->dataCollector->collectSubmittedData($this->form);
        $this->dataCollector->collectViewVariables($this->view);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            'view_vars' => 'bar',
            'config' => 'bar',
            'default_data' => 'bar',
            'submitted_data' => 'bar',
            'children' => array(),
        );

        $formData = array(
            'view_vars' => 'foo',
            'config' => 'foo',
            'default_data' => 'foo',
            'submitted_data' => 'foo',
            'children' => array(
                'child' => $childFormData,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testFinalFormReliesOnFormViewStructure()
    {
        $this->form->add($child1 = $this->createForm('first'));
        $this->form->add($child2 = $this->createForm('second'));

        $this->view->children['second'] = $this->childView;

        $this->dataCollector->buildPreliminaryFormTree($this->form);

        $child1Data = array(
            'children' => array(),
        );

        $child2Data = array(
            'children' => array(),
        );

        $formData = array(
            'children' => array(
                'first' => $child1Data,
                'second' => $child2Data,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($child1) => $child1Data,
                spl_object_hash($child2) => $child2Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());

        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $formData = array(
            'children' => array(
                // "first" not present in FormView
                'second' => $child2Data,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($child1) => $child1Data,
                spl_object_hash($child2) => $child2Data,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testChildViewsCanBeWithoutCorrespondingChildForms()
    {
        // don't add $this->childForm to $this->form!

        $this->view->children['child'] = $this->childView;

        $form = $this->form;
        $childForm = $this->childForm;
        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('config' => 'foo');
                if ($f === $childForm) return array('config' => 'bar');
                return array();
            });

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            // no "config" key
            'children' => array(),
        );

        $formData = array(
            'config' => 'foo',
            'children' => array(
                'child' => $childFormData,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                // no child entry
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testChildViewsWithoutCorrespondingChildFormsMayBeExplicitlyAssociated()
    {
        // don't add $this->childForm to $this->form!

        $this->view->children['child'] = $this->childView;

        // but associate the two
        $this->dataCollector->associateFormWithView($this->childForm, $this->childView);

        $form = $this->form;
        $childForm = $this->childForm;
        $this->dataExtractor->expects($this->any())
            ->method('extractConfiguration')
            ->willReturnCallback(function ($f) use ($form, $childForm) {
                if ($f === $form) return array('config' => 'foo');
                if ($f === $childForm) return array('config' => 'bar');
                return array();
            });

        // explicitly call collectConfiguration(), since $this->childForm is not
        // contained in the form tree
        $this->dataCollector->collectConfiguration($this->form);
        $this->dataCollector->collectConfiguration($this->childForm);
        $this->dataCollector->buildFinalFormTree($this->form, $this->view);

        $childFormData = array(
            'config' => 'bar',
            'children' => array(),
        );

        $formData = array(
            'config' => 'foo',
            'children' => array(
                'child' => $childFormData,
            ),
        );

        $this->assertSame(array(
            'forms' => array(
                'name' => $formData,
            ),
            'forms_by_hash' => array(
                spl_object_hash($this->form) => $formData,
                spl_object_hash($this->childForm) => $childFormData,
            ),
            'nb_errors' => 0,
        ), $this->dataCollector->getData());
    }

    public function testCollectSubmittedDataCountsErrors()
    {
        $form1 = $this->createForm('form1');
        $childForm1 = $this->createForm('child1');
        $form2 = $this->createForm('form2');

        $form1->add($childForm1);
        $this->dataExtractor
             ->method('extractConfiguration')
             ->willReturn(array());
        $this->dataExtractor
             ->method('extractDefaultData')
->willReturn(array());
        $this->dataExtractor->expects($this->any())
            ->method('extractSubmittedData')
            ->willReturnCallback(function ($f) use ($form1, $childForm1, $form2) {
if ($f === $form1) return array('errors' => array('foo'));
if ($f === $childForm1) return array('errors' => array('bar', 'bam'));
if ($f === $form2) return array('errors' => array('baz'));
                return array();
            });

        $this->dataCollector->collectSubmittedData($form1);

        $data = $this->dataCollector->getData();
        $this->assertSame(3, $data['nb_errors']);

        $this->dataCollector->collectSubmittedData($form2);

        $data = $this->dataCollector->getData();
        $this->assertSame(4, $data['nb_errors']);
    }

    private function createForm($name)
    {
        $builder = new FormBuilder($name, null, $this->dispatcher, $this->factory);
        $builder->setCompound(true);
        $builder->setDataMapper($this->dataMapper);

        return $builder->getForm();
    }
}
