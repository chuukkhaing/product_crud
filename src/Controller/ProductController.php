<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use League\Csv\Reader;
use League\Csv\Writer;

#[Route('/')]
class ProductController extends AbstractController
{
    private $entityManager;
    private $productRepository;
    
    public function __construct(EntityManagerInterface $entityManager, ProductRepository $productRepository)
    {
        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
    }

    #[Route('/', name: 'product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository, PaginatorInterface $paginator): Response
    {
        $filters = [
            'name' => $request->query->get('name'),
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'stockQuantity' => $request->query->get('stockQuantity'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo')
        ];
        $sortField = $request->query->get('sortField', 'createdDatetime');
        $sortOrder = $request->query->get('sortOrder', 'DESC');
        $query = $this->productRepository->findByFilters($filters, $sortField, $sortOrder, 10, 0);

        $pagination = $paginator->paginate(
            $query, /* query NOT result */
            $request->query->getInt('page', 1), /*page number*/
            10 /*limit per page*/
        );

        return $this->render('product/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($product);
            $this->entityManager->flush();

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }
    
    #[Route('/{id}', name: 'product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('product_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($product);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('product_index');
    }

    #[Route('/product/import', name: 'product_import', methods: ['GET', 'POST'])]
    public function import(Request $request, EntityManagerInterface $entityManager): Response
    {
        $file = $request->files->get('importFile');

        if ($file && $file->isValid() && $file->getClientOriginalExtension() === 'csv') {
            $handle = fopen($file->getPathname(), 'r');

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $product = new Product();
                $product->setName($row[1]);
                $product->setDescription($row[2]);
                $product->setPrice((float)$row[3]);
                $product->setStockQuantity($row[4]);
                $product->setCreatedDatetime(new \DateTime($row[5]));
                $entityManager->persist($product);
            }

            fclose($handle);
            $entityManager->flush();

            $this->addFlash('success', 'Products imported successfully.');
        } else {
            $this->addFlash('danger', 'Invalid file or file format.');
        }

        return $this->redirectToRoute('product_index');
    }

    #[Route('/product/export', name: 'product_export', methods: ['GET'])]
    public function export(ProductRepository $productRepository): Response
    {
        // Fetch all products
        $products = $productRepository->findAll();

        // Convert products to CSV
        $csvData = [];

        foreach ($products as $product) {
            $csvData[] = [
                $product->getId(),
                $product->getName(),
                $product->getDescription(),
                $product->getPrice(),
                $product->getStockQuantity(),
                $product->getCreatedDatetime()->format('Y-m-d H:i:s')
            ];
        }

        // Create a response with CSV content
        $response = new Response($this->arrayToCsv($csvData));
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="products.csv"');

        return $response;
    }

    private function arrayToCsv(array $data): string
    {
        $output = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
