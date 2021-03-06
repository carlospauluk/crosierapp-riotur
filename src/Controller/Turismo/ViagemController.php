<?php

namespace App\Controller\Turismo;

use App\Entity\Turismo\Passageiro;
use App\Entity\Turismo\Viagem;
use App\EntityHandler\Turismo\PassageiroEntityHandler;
use App\EntityHandler\Turismo\ViagemEntityHandler;
use App\Form\Turismo\PassageiroType;
use App\Form\Turismo\ViagemType;
use CrosierSource\CrosierLibBaseBundle\Controller\FormListController;
use CrosierSource\CrosierLibBaseBundle\Exception\ViewException;
use CrosierSource\CrosierLibBaseBundle\Utils\ExceptionUtils\ExceptionUtils;
use CrosierSource\CrosierLibBaseBundle\Utils\RepositoryUtils\FilterData;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * @author Carlos Eduardo Pauluk
 */
class ViagemController extends FormListController
{

    /** @var PassageiroEntityHandler */
    private $passageiroEntityHandler;

    /**
     * @required
     * @param ViagemEntityHandler $entityHandler
     */
    public function setEntityHandler(ViagemEntityHandler $entityHandler): void
    {
        $this->entityHandler = $entityHandler;
    }

    /**
     * @required
     * @param PassageiroEntityHandler $passageiroEntityHandler
     */
    public function setPassageiroEntityHandler(PassageiroEntityHandler $passageiroEntityHandler): void
    {
        $this->passageiroEntityHandler = $passageiroEntityHandler;
    }


    public function getFilterDatas(array $params): array
    {
        return [
            new FilterData(['pedido'], 'LIKE', 'pedido', $params)
        ];
    }

    /**
     *
     * @Route("/tur/viagem/form/{id}", name="viagem_form", defaults={"id"=null}, requirements={"id"="\d+"})
     * @param Request $request
     * @param Viagem|null $viagem
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     *
     * @IsGranted({"ROLE_TURISMO_ADMIN"}, statusCode=403)
     */
    public function form(Request $request, Viagem $viagem = null)
    {
        $params = [
            'typeClass' => ViagemType::class,
            'formView' => 'Turismo/viagem_form.html.twig',
            'formRoute' => 'viagem_form',
            'formPageTitle' => 'Viagem'
        ];
        return $this->doForm($request, $viagem, $params);
    }

    /**
     *
     * @Route("/tur/viagem/list/", name="viagem_list")
     * @param Request $request
     * @return Response
     * @throws \Exception
     *
     * @IsGranted({"ROLE_TURISMO_ADMIN"}, statusCode=403)
     */
    public function list(Request $request): Response
    {
        $params = [
            'formRoute' => 'viagem_form',
            'listView' => '@CrosierLibBase/list.html.twig',
            'listJS' => 'Turismo/viagemList.js',
            'listRoute' => 'viagem_list',
            'listRouteAjax' => 'viagem_datatablesJsList',
            'listPageTitle' => 'Viagens',
            'deleteRoute' => 'viagem_delete',
            'listId' => 'viagemList'
        ];
        return $this->doList($request, $params);
    }

    /**
     *
     * @Route("/tur/viagem/datatablesJsList/", name="viagem_datatablesJsList")
     * @param Request $request
     * @return Response
     * @throws ViewException
     *
     * @IsGranted({"ROLE_TURISMO_ADMIN"}, statusCode=403)
     */
    public function datatablesJsList(Request $request): Response
    {
        return $this->doDatatablesJsList($request);
    }

    /**
     *
     * @Route("/tur/viagem/delete/{id}/", name="viagem_delete", requirements={"id"="\d+"})
     * @param Request $request
     * @param Viagem $viagem
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @IsGranted({"ROLE_TURISMO_ADMIN"}, statusCode=403)
     */
    public function delete(Request $request, Viagem $viagem): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->doDelete($request, $viagem);
    }


    /**
     *
     * @Route("/tur/viagem/passageiro/form/{viagem}/{passageiro}", name="viagem_passageiro_form", defaults={"passageiro"=null}, requirements={"viagem"="\d+","passageiro"="\d+"})
     *
     * @ParamConverter("viagem", class="App\Entity\Turismo\Viagem", options={"id" = "viagem"})
     * @ParamConverter("passageiro", class="App\Entity\Turismo\Passageiro", options={"id" = "passageiro"})
     * @param Request $request
     * @param Viagem|null $viagem
     * @param Passageiro|null $passageiro
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     * @IsGranted({"ROLE_TURISMO_ADMIN"}, statusCode=403)
     */
    public function passageiroForm(Request $request, Viagem $viagem, ?Passageiro $passageiro = null)
    {
        $params = [
            'formView' => 'Turismo/viagem_formPassageiro.html.twig',
            'formJS' => 'Turismo/viagemPassageiroForm.js',
            'formRoute' => 'viagem_passageiro_form',
            'formRouteParams' => ['viagem' => $viagem->getId()],
            'page_title' => 'Passageiro',
            'formPageTitle' => 'Passageiro',
            'formPageSubTitle' => 'Viagem: ' . $viagem->getItinerario()->getDescricaoMontada(),
            'viagem' => $viagem
        ];

        $form = $this->createForm(PassageiroType::class, $passageiro);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    /** @var Passageiro $passageiro */
                    $passageiro = $form->getData();
                    $passageiro->setViagem($viagem);
                    $passageiro = $this->passageiroEntityHandler->save($passageiro);
                    $this->addFlash('success', 'Registro salvo com sucesso!');
                    return $this->redirectToRoute('viagem_form', ['id' => $viagem->getId(), '_fragment' => 'passageiros']);
                } catch (ViewException $e) {
                    $this->addFlash('error', $e->getMessage());
                } catch (\Exception $e) {
                    $msg = ExceptionUtils::treatException($e);
                    $this->addFlash('error', $msg);
                    $this->addFlash('error', 'Erro ao salvar!');
                }
            } else {
                $errors = $form->getErrors(true, true);
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        // Pode ou não ter vindo algo no $params. Independentemente disto, só adiciono form e foi-se.
        $params['form'] = $form->createView();
        $params['e'] = $passageiro;

        return $this->doRender($params['formView'], $params);

    }


    /**
     *
     * @Route("/tur/viagem/passageiro/delete/{passageiro}", name="viagem_passageiro_delete", requirements={"passageiro"="\d+"})
     *
     * @ParamConverter("passageiro", class="App\Entity\Turismo\Passageiro", options={"id" = "passageiro"})
     *
     * @param Request $request
     * @param Passageiro $passageiro
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @IsGranted({"ROLE_TURISMO_ADMIN"}, statusCode=403)
     */
    public function passageiroDelete(Request $request, Passageiro $passageiro): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        try {
            $this->passageiroEntityHandler->delete($passageiro);
            $this->addFlash('success', 'Passageiro removido com sucesso!');
        } catch (ViewException $e) {
            $this->addFlash('error', $e->getMessage());
        }

    }


}