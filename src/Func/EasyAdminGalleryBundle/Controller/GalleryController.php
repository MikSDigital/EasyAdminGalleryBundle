<?php

namespace Func\EasyAdminGalleryBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GalleryController extends BaseAdminController
{
    /**
     * Drag and drop action
     * Upload files and create records
     * @return JsonResponse
     */
    public function dragDropAction()
    {
        $entityConfig = $this->entity;

        $form = $this->getDragDropForm();
        $form->handleRequest($this->request);
        if ($form->isSubmitted() && $form->isValid())
        {
            $form = $form->getData();

            /** @var FileBag|UploadedFile[] $files */
            $files = $form['__files__'];

            unset($form['__files__']);

            foreach ($files as $file)
            {
                $variables = [
                    '%file%' => $file,
                    '%file.name%' => pathinfo($file->getClientOriginalName())['filename'],
                ];

                $entity = $this->executeDynamicMethod('createNew<EntityName>Entity');
                $values = array_merge($entityConfig['dragDrop']['values'], $form);

                foreach ($values as $key => $value)
                {
                    if (isset($variables[$value]))
                        $value = $variables[$value];
                    else if (is_string($value))
                        $value = str_replace(array_keys($variables), array_values($variables), $value);

                    call_user_func([$entity, 'set'.ucfirst($key)], $value);
                }

                $this->dispatch(EasyAdminEvents::PRE_PERSIST, ['entity' => $entity]);

                $this->executeDynamicMethod('prePersist<EntityName>Entity', [$entity]);

                $this->em->persist($entity);
                $this->em->flush();

                $this->dispatch(EasyAdminEvents::POST_PERSIST, ['entity' => $entity]);
            }
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }

    /**
     * Get drag and drop form
     * @return FormInterface
     */
    private function getDragDropForm()
    {
        $dragDropForm = $this->get('form.factory')->createNamedBuilder('dragDrop')
            ->add('__files__', FileType::class, ['multiple' => true])
        ;

        /** @var Form $newForm */
        $newForm = $this->executeDynamicMethod(
            'create<EntityName>NewForm', [
            $this->executeDynamicMethod('createNew<EntityName>Entity'),
            $this->entity['new']['fields']
        ]);

        foreach ($this->entity['new']['fields'] as $key => $field)
        {
            if (in_array($key, $this->entity['dragDrop']['fields']))
            {
                $config = $newForm->get($key)->getConfig();
                $dragDropForm->add(
                    $key,
                    get_class($config->getType()->getInnerType()),
                    $config->getOptions())
                ;
            }
        }

        return $dragDropForm->getForm();
    }

    /**
     * Add drag and drop form to view parameters
     * {@inheritdoc}
     */
    protected function render($view, array $parameters = array(), Response $response = null)
    {
        if ($this->request->query->get('action') == 'list')
            $parameters['drag_drop_form'] = $this->getDragDropForm()->createView();
        return parent::render($view, $parameters, $response);
    }

    // templates
    // todo: viewAction()

    public function listAction()
    {
        $this->entity['templates']['list'] = '@FuncEasyAdminGallery/list.html.twig';
        return parent::listAction();
    }

    public function searchAction()
    {
        $this->entity['templates']['list'] = '@FuncEasyAdminGallery/list.html.twig';
        return parent::searchAction();
    }
}