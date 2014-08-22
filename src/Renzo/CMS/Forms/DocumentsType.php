<?php
/*
 * Copyright REZO ZERO 2014
 *
 *
 * @file DocumentsType.php
 * @copyright REZO ZERO 2014
 * @author Ambroise Maupate
 */
namespace RZ\Renzo\CMS\Forms;

use RZ\Renzo\Core\Kernel;
use RZ\Renzo\Core\Entities\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Document selector and uploader form field type.
 */
class DocumentsType extends AbstractType
{
    protected $selectedDocuments;

    /**
     * {@inheritdoc}
     *
     * @param array $documents Array of Document instances
     */
    public function __construct(array $documents)
    {
        $this->selectedDocuments = $documents;
    }

    /**
     * {@inheritdoc}
     *
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions( OptionsResolverInterface $resolver )
    {
        $documents = Kernel::getInstance()->em()
            ->getRepository('RZ\Renzo\Core\Entities\Document')
            ->findAll();

        $choices = array();
        foreach ($documents as $doc) {
            $choices[$doc->getId()] = $doc->getFilename();
        }

        $datas = array();
        foreach ($this->selectedDocuments as $doc) {
            $datas[] = $doc->getId();
        }

        $resolver->setDefaults(array(
            'choices' => $choices,
            'multiple' => true
        ));
    }

    /**
     * {@inheritdoc}
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);

        /*
         * Inject data as plain documents entities
         */
        $view->vars['data'] = $this->selectedDocuments;
    }
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'documents';
    }
}