<?php
/*
 * Copyright REZO ZERO 2014
 *
 *
 * @file TagsController.php
 * @copyright REZO ZERO 2014
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Controllers;

use Themes\Rozier\RozierApp;
use RZ\Roadiz\Core\Kernel;
use RZ\Roadiz\Core\Entities\Tag;
use RZ\Roadiz\Core\Entities\TagTranslation;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\ListManagers\EntityListManager;
use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;
use RZ\Roadiz\Core\Utils\StringHandler;
use Themes\Rozier\Widgets\TagTreeWidget;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * {@inheritdoc}
 */
class TagsController extends RozierApp
{
    /**
     * List every tags.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');

        /*
         * Manage get request to filter list
         */
        $listManager = new EntityListManager(
            $request,
            $this->getService('em'),
            'RZ\Roadiz\Core\Entities\Tag'
        );
        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['tags'] = $listManager->getEntities();

        return new Response(
            $this->getTwig()->render('tags/list.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
    }

    /**
     * Return an edition form for current translated tag.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param integer                                  $tagId
     * @param integer | null                           $translationId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function editTranslatedAction(Request $request, $tagId, $translationId = null)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');

        if (null === $translationId) {
            $translation = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                ->findDefault();
        } else {
            $translation = $this->getService('em')
                    ->find('RZ\Roadiz\Core\Entities\Translation', (int) $translationId);
        }

        if (null !== $translation) {

            /*
             * Here we need to directly select tagTranslation
             * if not doctrine will grab a cache tag because of TagTreeWidget
             * that is initialized before calling route method.
             */
            $gtag = $this->getService('em')
                ->find('RZ\Roadiz\Core\Entities\Tag', (int) $tagId);

            $tt = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\TagTranslation')
                ->findOneBy(array('translation'=>$translation, 'tag'=>$gtag));


            if (null !== $tt) {
                /*
                 * Tag is already translated
                 */
                $tag = $tt->getTag();

                $this->assignation['tag'] = $tag;
                $this->assignation['translatedTag'] = $tt;
                $this->assignation['translation'] = $translation;
                $this->assignation['available_translations'] = $this->getService('em')
                    ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                    ->findAllAvailable();

                $form = $this->buildEditForm($tag, $tt);

                $form->handleRequest();

                if ($form->isValid()) {

                    $this->editTag($form->getData(), $tt);

                    $msg = $this->getTranslator()->trans('tag.%name%.updated', array(
                        '%name%'=>$tag->getTranslatedTags()->first()->getName()
                    ));
                    $request->getSession()->getFlashBag()->add('confirm', $msg);
                    $this->getService('logger')->info($msg);
                    /*
                     * Force redirect to avoid resending form when refreshing page
                     */
                    $response = new RedirectResponse(
                        $this->getService('urlGenerator')->generate(
                            'tagsEditTranslatedPage',
                            array('tagId' => $tag->getId(), 'translationId' => $translation->getId())
                        )
                    );
                    $response->prepare($request);

                    return $response->send();
                }

                $this->assignation['form'] = $form->createView();

            } else {
                /*
                 * If translation does not exist, we created it.
                 */
                $this->getService('em')->refresh($gtag);

                if ($gtag !== null) {

                    $baseTranslation = $gtag->getTranslatedTags()->first();

                    $translatedTag = new TagTranslation($gtag, $translation);

                    if (false !== $baseTranslation) {
                        $translatedTag->setName($baseTranslation->getName());
                    } else {
                        $translatedTag->setName('tag_'.$gtag->getId());
                    }
                    $this->getService('em')->persist($translatedTag);
                    $this->getService('em')->flush();

                    $response = new RedirectResponse(
                        $this->getService('urlGenerator')->generate(
                            'tagsEditTranslatedPage',
                            array(
                                'tagId' => $gtag->getId(),
                                'translationId' => $translation->getId()
                            )
                        )
                    );
                    $response->prepare($request);

                    return $response->send();

                } else {
                    return $this->throw404();
                }
            }


            return new Response(
                $this->getTwig()->render('tags/edit.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }

    /**
     * Return an creation form for requested tag.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');

        $tag = new Tag();

        $translation = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                ->findDefault();

        if ($tag !== null &&
            $translation !== null) {

            $this->assignation['tag'] = $tag;
            $form = $this->buildAddForm($tag);

            $form->handleRequest();

            if ($form->isValid()) {
                $this->addTag($form->getData(), $tag, $translation);

                $msg = $this->getTranslator()->trans('tag.%name%.created', array('%name%'=>$tag->getTagName()));
                $request->getSession()->getFlashBag()->add('confirm', $msg);
                $this->getService('logger')->info($msg);
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate('tagsHomePage')
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['form'] = $form->createView();

            return new Response(
                $this->getTwig()->render('tags/add.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }

    /**
     * Return a edition form for requested tag settings .
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $tagId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function editSettingsAction(Request $request, $tagId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');

        $translation = $this->getService('em')
            ->getRepository('RZ\Roadiz\Core\Entities\Translation')
            ->findDefault();

        $tag = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Tag', (int) $tagId);

        if ($tag !== null) {

            $form = $this->buildEditSettingsForm($tag);

            $form->handleRequest();

            if ($form->isValid()) {
                $this->editTagSettings($form->getData(), $tag);

                $msg = $this->getTranslator()->trans('tag.%name%.updated', array('%name%'=>$tag->getTagName()));
                $request->getSession()->getFlashBag()->add('confirm', $msg);
                $this->getService('logger')->info($msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate(
                        'tagsSettingsPage',
                        array('tagId' => $tag->getId())
                    )
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['form'] = $form->createView();
            $this->assignation['tag'] = $tag;
            $this->assignation['translation'] = $translation;

            return new Response(
                $this->getTwig()->render('tags/settings.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        }
    }

    /**
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $tagId
     * @param int                                      $translationId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function treeAction(Request $request, $tagId, $translationId = null)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');

        $tag = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Tag', (int) $tagId);
        $this->getService('em')->refresh($tag);

        $translation = null;
        if (null !== $translationId) {
            $translation = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                ->findOneBy(array('id'=>(int) $translationId));
        } else {
            $translation = $this->getService('em')
                    ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                    ->findDefault();
        }

        if (null !== $tag) {
            $widget = new TagTreeWidget($request, $this, $tag);
            $this->assignation['tag'] = $tag;
            $this->assignation['translation'] = $translation;
            $this->assignation['specificTagTree'] = $widget;
        }

        return new Response(
            $this->getTwig()->render('tags/tree.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
    }

    /**
     * Return a deletion form for requested tag.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $tagId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $tagId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS_DELETE');

        $tag = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Tag', (int) $tagId);

        if ($tag !== null &&
            !$tag->isLocked()) {
            $this->assignation['tag'] = $tag;

            $form = $this->buildDeleteForm($tag);
            $form->handleRequest();

            if ($form->isValid() &&
                $form->getData()['tagId'] == $tag->getId()) {

                $this->deleteTag($form->getData(), $tag);
                $msg = $this->getTranslator()->trans('tag.%name%.deleted', array('%name%'=>$tag->getTranslatedTags()->first()->getName()));
                $request->getSession()->getFlashBag()->add('confirm', $msg);
                $this->getService('logger')->info($msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate('tagsHomePage')
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['form'] = $form->createView();

            return new Response(
                $this->getTwig()->render('tags/delete.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }

    /**
     * Handle tag creation pages.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $tagId
     * @param int                                      $translationId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function addChildAction(Request $request, $tagId, $translationId = null)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');

        $translation = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                ->findDefault();

        if ($translationId != null) {
            $translation = $this->getService('em')
                ->find('RZ\Roadiz\Core\Entities\Translation', (int) $translationId);
        }
        $parentTag = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Tag', (int) $tagId);

        if ($translation !== null &&
            $parentTag !== null) {

            $form = $this->buildAddChildForm($parentTag);
            $form->handleRequest();

            if ($form->isValid()) {

                try {
                    $tag = $this->addChildTag($form->getData(), $parentTag, $translation);

                    $msg = $this->getTranslator()->trans('child.tag.%name%.created', array('%name%'=>$tag->getTagName()));
                    $request->getSession()->getFlashBag()->add('confirm', $msg);
                    $this->getService('logger')->info($msg);

                    $response = new RedirectResponse(
                        $this->getService('urlGenerator')->generate(
                            'tagsEditPage',
                            array('tagId' => $tag->getId())
                        )
                    );
                    $response->prepare($request);

                    return $response->send();
                } catch (EntityAlreadyExistsException $e) {

                    $request->getSession()->getFlashBag()->add('error', $e->getMessage());
                    $this->getService('logger')->warning($e->getMessage());

                    $response = new RedirectResponse(
                        $this->getService('urlGenerator')->generate(
                            'tagsAddChildPage',
                            array('tagId' => $tagId, 'translationId' => $translationId)
                        )
                    );
                    $response->prepare($request);

                    return $response->send();
                }
            }

            $this->assignation['translation'] = $translation;
            $this->assignation['form'] = $form->createView();
            $this->assignation['parentTag'] = $parentTag;

            return new Response(
                $this->getTwig()->render('tags/add.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }

    /**
     * Handle tag nodes page.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $tagId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function editNodesAction(Request $request, $tagId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_TAGS');
        $tag = $this->getService('em')
                    ->find('RZ\Roadiz\Core\Entities\Tag', (int) $tagId);

        if (null !== $tag) {

            $translation = $this->getService('em')
                    ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                    ->findDefault();

            $this->assignation['tag'] = $tag;

            /*
             * Manage get request to filter list
             */
            $listManager = new EntityListManager(
                $request,
                $this->getService('em'),
                'RZ\Roadiz\Core\Entities\Node',
                array(
                    'tags' => $tag
                )
            );
            $listManager->handle();

            $this->assignation['filters'] = $listManager->getAssignation();
            $this->assignation['nodes'] = $listManager->getEntities();

            $this->assignation['translation'] = $translation;

            return new Response(
                $this->getTwig()->render('tags/nodes.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );

        } else {

            return $this->throw404();
        }
    }

    /**
     * @param array                                 $data
     * @param RZ\Roadiz\Core\Entities\TagTranslation $tag
     *
     * @throws EntityAlreadyExistsException
     */
    private function editTag($data, TagTranslation $translatedTag)
    {
        if ($translatedTag->getTranslation()->getId() !=
            $data['translation']) {
            throw new \RuntimeException("Translations don't match.", 1);
        }
        foreach ($data as $key => $value) {
            $setter = 'set'.ucwords($key);

            if ($key != 'translation') {
                $translatedTag->$setter( $value );
            }
        }

        $this->getService('em')->flush();
    }

    /**
     * @param array                      $data
     * @param RZ\Roadiz\Core\Entities\Tag $tag
     *
     * @throws EntityAlreadyExistsException
     */
    private function editTagSettings($data, Tag $tag)
    {
        if ($tag->getTagName() != $data['tagName'] &&
            $this->checkExists($data['tagName'])) {
            throw new EntityAlreadyExistsException(
                $this->getTranslator()->trans(
                    'tag.%name%.no_update.already_exists',
                    array('%name%'=>$data['tagName'])
                ),
                1
            );
        }

        foreach ($data as $key => $value) {
            $setter = 'set'.ucwords($key);
            $tag->$setter($value);
        }

        $this->getService('em')->flush();
    }

    /**
     * @param array                              $data
     * @param RZ\Roadiz\Core\Entities\Tag         $tag
     * @param RZ\Roadiz\Core\Entities\Translation $translation
     *
     * @return RZ\Roadiz\Core\Entities\Tag
     * @throws EntityAlreadyExistsException
     */
    private function addTag($data, Tag $tag, Translation $translation)
    {
        if ($this->checkExists($data['name'])) {
            throw new EntityAlreadyExistsException(
                $this->getTranslator()->trans(
                    'tag.%name%.no_creation.already_exists',
                    array('%name%'=>$data['name'])
                ),
                1
            );
        }

        $translatedTag = new TagTranslation($tag, $translation);

        foreach ($data as $key => $value) {
            $setter = 'set'.ucwords($key);

            if ($key == 'name' || $key == 'description') {
                $translatedTag->$setter( $value );
            } else {
                $tag->$setter($value);
            }
        }
        /*
         * Use the same name for tagName key
         */
        $tag->setTagName($data['name']);

        $tag->getTranslatedTags()->add($translatedTag);

        $this->getService('em')->persist($translatedTag);
        $this->getService('em')->persist($tag);
        $this->getService('em')->flush();

        return $tag;
    }

    /**
     * Check if a tag already uses this tagName.
     *
     * @param string $name
     *
     * @return boolean
     */
    private function checkExists($name)
    {
        $ttag = $this->getService('em')
                    ->getRepository('RZ\Roadiz\Core\Entities\Tag')
                    ->findOneBy(array('tagName'=>StringHandler::slugify($name)));

        return $ttag !== null;
    }

    /**
     * @param array                              $data
     * @param RZ\Roadiz\Core\Entities\Tag         $parentTag
     * @param RZ\Roadiz\Core\Entities\Translation $translation
     *
     * @return RZ\Roadiz\Core\Entities\Tag
     * @throws EntityAlreadyExistsException
     */
    private function addChildTag($data, Tag $parentTag, Translation $translation)
    {

        if ($parentTag->getId() != $data['parent_tagId']) {
            throw new \RuntimeException("Parent tag Ids do not match", 1);
        }
        if ($this->checkExists($data['name'])) {
            throw new EntityAlreadyExistsException(
                $this->getTranslator()->trans(
                    'tag.%name%.already_added',
                    array('%name%'=>$data['name'])
                ),
                1
            );
        }

        $tag = new Tag();
        $tag->setParent($parentTag);
        $tag->setTagName($data['name']);

        $translatedTag = new TagTranslation($tag, $translation);

        foreach ($data as $key => $value) {

            $setter = 'set'.ucwords($key);

            if ($key == 'name' || $key == 'description') {
                $translatedTag->$setter($value);
            } elseif ($key != 'parent_tagId') {
                $tag->$setter($value);
            }
        }
        $tag->getTranslatedTags()->add($translatedTag);

        $this->getService('em')->persist($translatedTag);
        $this->getService('em')->persist($tag);
        $this->getService('em')->flush();

        return $tag;
    }

    /**
     * @param array                      $data
     * @param RZ\Roadiz\Core\Entities\Tag $tag
     */
    private function deleteTag($data, Tag $tag)
    {
        $this->getService('em')->remove($tag);
        $this->getService('em')->flush();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Tag $tag
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildAddForm(Tag $tag)
    {
        $defaults = array(
            'visible' => $tag->isVisible(),
            'locked' => $tag->isLocked()
        );

        $builder = $this->getService('formFactory')
                    ->createBuilder('form', $defaults)
                    ->add('name', 'text', array(
                        'label' => $this->getTranslator()->trans('name'),
                        'constraints' => array(
                            new NotBlank()
                        )
                    ))
                    ->add('locked', 'checkbox', array(
                        'label' => $this->getTranslator()->trans('locked'),
                        'required' => false
                    ))
                    ->add('visible', 'checkbox', array(
                        'label' => $this->getTranslator()->trans('visible'),
                        'required' => false
                    ))
                    ->add('description', new \RZ\Roadiz\CMS\Forms\MarkdownType(), array(
                        'label' => $this->getTranslator()->trans('description'),
                        'required' => false
                    ));

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Tag $tag
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildAddChildForm(Tag $tag)
    {
        $defaults = array(
            'visible' => $tag->isVisible()
        );

        $builder = $this->getService('formFactory')
                    ->createBuilder('form', $defaults)
                    ->add('name', 'text', array(
                        'label' => $this->getTranslator()->trans('name'),
                        'constraints' => array(
                            new NotBlank()
                        )
                    ))
                    ->add('visible', 'checkbox', array(
                        'label' => $this->getTranslator()->trans('visible'),
                        'required' => false
                    ))
                    ->add('description', new \RZ\Roadiz\CMS\Forms\MarkdownType(), array(
                        'label' => $this->getTranslator()->trans('description'),
                        'required' => false
                    ))
                    ->add('parent_tagId', 'hidden', array(
                        'label' => $this->getTranslator()->trans('parent_tagId'),
                        "data" => $tag->getId(),
                        'required' => true
                    ));

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Tag            $tag
     * @param RZ\Roadiz\Core\Entities\TagTranslation $tt
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildEditForm(Tag $tag, TagTranslation $tt)
    {
        $defaults = array(
            'name' => $tt->getName(),
            'description' => $tt->getDescription(),
            'translation' => $tt->getTranslation()->getId(),
        );

        $builder = $this->getService('formFactory')
            ->createBuilder('form', $defaults)
            ->add('name', 'text', array(
                'label' => $this->getTranslator()->trans('name'),
                'constraints' => array(
                    new NotBlank()
                )
            ))
            ->add('description', new \RZ\Roadiz\CMS\Forms\MarkdownType(), array(
                'label' => $this->getTranslator()->trans('description'),
                'required' => false
            ))
            ->add('translation', 'hidden', array(
                'label' => false,
                'data' => $tt->getTranslation()->getId()
            ));

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Tag $tag
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildEditSettingsForm(Tag $tag)
    {
        $defaults = array(
            'tagName' => $tag->getTagName(),
            'visible' => $tag->isVisible(),
            'locked' => $tag->isLocked()
        );

        $builder = $this->getService('formFactory')
            ->createBuilder('form', $defaults)
            ->add('tagName', 'text', array(
                'label' => $this->getTranslator()->trans('tagName'),
                'constraints' => array(
                    new NotBlank()
                )
            ))
            ->add('visible', 'checkbox', array(
                'label' => $this->getTranslator()->trans('visible'),
                'required' => false
            ))
            ->add('locked', 'checkbox', array(
                'label' => $this->getTranslator()->trans('locked'),
                'required' => false
            ));

        return $builder->getForm();
    }

    /**
     * @param RZ\Roadiz\Core\Entities\Tag $tag
     *
     * @return \Symfony\Component\Form\Form
     */
    private function buildDeleteForm(Tag $tag)
    {
        $builder = $this->getService('formFactory')
            ->createBuilder('form')
            ->add('tagId', 'hidden', array(
                'data' => $tag->getId(),
                'constraints' => array(
                    new NotBlank()
                )
            ));

        return $builder->getForm();
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public static function getTags()
    {
        return Kernel::getService('em')
            ->getRepository('RZ\Roadiz\Core\Entities\Tag')
            ->findAll();
    }
}
