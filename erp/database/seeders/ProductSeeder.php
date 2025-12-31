<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Category;
use App\Models\Supplier;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->warn('No tenant found. Skipping ProductSeeder.');
            return;
        }

        // Build category and supplier maps
        $categories = Category::where('tenant_id', $tenant->id)->pluck('id', 'name')->toArray();
        $suppliers = Supplier::where('tenant_id', $tenant->id)->pluck('id', 'name')->toArray();

        $products = [
            ['barcode' => '75930288', 'name' => 'Queso Rikesa 200gr', 'price' => 3490, 'stock' => 10, 'cost' => 2521, 'category' => 'Alimentos', 'supplier' => 'Global Ve', 'image' => 'https://tiendaslistto.cl/erp/img/1757464084_x8paf04yowsh6mw0vhzvrnmib04m.webp'],
            ['barcode' => '78005624', 'name' => 'Bigtime Menta Auto 11g', 'price' => 690, 'stock' => 50, 'cost' => 331, 'category' => 'Dulces', 'supplier' => 'Arcor', 'image' => 'https://dojiw2m9tvv09.cloudfront.net/89967/product/breaking-news-2025-05-16t085407-6230700.png'],
            ['barcode' => '78023994', 'name' => 'Bon O Bon Leche 15g', 'price' => 490, 'stock' => 30, 'cost' => 243, 'category' => 'Chocolates y Galletas', 'supplier' => 'Arcor', 'image' => 'https://papudo.ayfmarket.cl/wp-content/uploads/2022/01/Chocolate-Bon-o-bon-Leche-15-g.jpeg'],
            ['barcode' => '78024106', 'name' => 'Bon O Bon Blanco 15gr', 'price' => 490, 'stock' => 26, 'cost' => 243, 'category' => 'Chocolates y Galletas', 'supplier' => 'Arcor', 'image' => 'https://dojiw2m9tvv09.cloudfront.net/11951/product/18270189820.jpg'],
            ['barcode' => '070847009511', 'name' => 'Monster Energy Lata 473ml', 'price' => 2190, 'stock' => 15, 'cost' => 1124, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://www.maicao.cl/dw/image/v2/BDPM_PRD/on/demandware.static/-/Sites-masterCatalog_Chile/default/dw65e7a8db/images/large/267735-bebida-normal-473-ml.jpg'],
            ['barcode' => '070847021964', 'name' => 'Monster Ultra Lata 473ml', 'price' => 2190, 'stock' => 12, 'cost' => 1124, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://unimarc.vtexassets.com/arquivos/ids/204577/000000000000603916-UN-02.jpg'],
            ['barcode' => '070847035800', 'name' => 'Monster Mango Loco Lata 473ml', 'price' => 2190, 'stock' => 18, 'cost' => 1124, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://i.bolder.run/r/czo0MDY0LGc6MTAwMHg/f0026e21/677409-70847035800.jpg'],
            ['barcode' => '83322052636', 'name' => 'Avena Alpina Original 1 Lt', 'price' => 4190, 'stock' => 8, 'cost' => 2876, 'category' => 'Lacteos', 'supplier' => 'Global Ve', 'image' => 'https://alpinaus.com/cdn/shop/files/Avena-Alpina-Original-Litro.jpg'],
            ['barcode' => '721883148527', 'name' => 'Malta Caracas', 'price' => 1490, 'stock' => 20, 'cost' => 723, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://tiendaslistto.cl/erp/img/1757464450_images (8).jpg'],
            ['barcode' => '745314559355', 'name' => 'Frescolita Lata 330 Ml', 'price' => 1690, 'stock' => 30, 'cost' => 814, 'category' => 'Bebidas', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSzg7A16HVC_ALUEFuyEjvLfuvRbEMDOBV_rh7yQWRjRx6ZzCu0FthFEkVpQjtPx-3pJQg'],
            ['barcode' => '757528049539', 'name' => 'Takis Blue 113gr', 'price' => 2190, 'stock' => 15, 'cost' => 938, 'category' => 'Snacks', 'supplier' => 'Ideal', 'image' => 'https://tiendaslistto.cl/erp/img/1757464399_takis-blue-113gr.jpg.webp'],
            ['barcode' => '7466762939041', 'name' => 'Pirulin Chocolate 24 Gr', 'price' => 990, 'stock' => 25, 'cost' => 490, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://barakibodegon.net/cdn/shop/files/Pirulin-16gr.webp'],
            ['barcode' => '7466762939010', 'name' => 'Pirulin Chocolate Lata 155 Gr', 'price' => 5990, 'stock' => 8, 'cost' => 3487, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://d1ks0wbvjr3pux.cloudfront.net/f6b0d96b-d7b1-4041-ba6c-4ee5f1f1f875-md.jpg'],
            ['barcode' => '7591016851555', 'name' => 'Chocolate Cri Cri Savoy 27 gr', 'price' => 1390, 'stock' => 25, 'cost' => 834, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://cdnx.jumpseller.com/tu-super-tm/image/15745006/resize/540/540'],
            ['barcode' => '7591016871065', 'name' => 'Susy 50 Gr', 'price' => 1390, 'stock' => 15, 'cost' => 655, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://tiendaslistto.cl/erp/img/1757464661_images.png'],
            ['barcode' => '7591016871089', 'name' => 'Cocosette 50gr', 'price' => 1390, 'stock' => 20, 'cost' => 550, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://www.distribuidoraeden.cl/wp-content/uploads/2020/09/COCOSETTE.jpg'],
            ['barcode' => '7702011022813', 'name' => 'Dandy 16gr', 'price' => 290, 'stock' => 50, 'cost' => 161, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://www.supermercadodelconfite.cl/cdn/shop/products/1308.jpg'],
            ['barcode' => '7501013118032', 'name' => 'Jumex Nectar Mango 335 Ml', 'price' => 1190, 'stock' => 25, 'cost' => 690, 'category' => 'Jugos', 'supplier' => 'Global Ve', 'image' => 'https://alvicl.vtexassets.com/arquivos/ids/161695/000000000000193108-UN-01.jpg'],
            ['barcode' => '7501013118117', 'name' => 'Jumex Nectar Piña 335 Ml', 'price' => 1190, 'stock' => 20, 'cost' => 690, 'category' => 'Bebidas', 'supplier' => 'Global Ve', 'image' => 'https://alvicl.vtexassets.com/arquivos/ids/161694/000000000000192833-UN-01.jpg'],
            ['barcode' => '7613030612339', 'name' => 'Super 8 Nestle 29gr', 'price' => 590, 'stock' => 30, 'cost' => 249, 'category' => 'Chocolates y Galletas', 'supplier' => 'Distribuidores', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTkeOvVLx4Cq79nza2xG_cSpJM4KEKh_gobXw'],
            ['barcode' => '7622201693114', 'name' => 'Oreo Rollo Cookies Cream 108 Gr', 'price' => 1190, 'stock' => 35, 'cost' => 677, 'category' => 'Chocolates y Galletas', 'supplier' => 'Inversiones VIA K', 'image' => 'https://tiendaslistto.cl/erp/img/1757464696_636915f57244b080202686.jpg'],
            ['barcode' => '7622201693152', 'name' => 'Oreo Rollo Chocolate 108gr', 'price' => 1190, 'stock' => 20, 'cost' => 601, 'category' => 'Chocolates y Galletas', 'supplier' => 'Inversiones VIA K', 'image' => 'https://distribuidoraonline.cl/wp-content/uploads/2022/03/oreo.jpg'],
            ['barcode' => '7801610000571', 'name' => 'Coca Cola 591ml', 'price' => 1390, 'stock' => 50, 'cost' => 788, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://unimarc.vtexassets.com/arquivos/ids/248652/000000000000610484-UN-02.jpg'],
            ['barcode' => '7801610000601', 'name' => 'Coca Cola Sin Azucar 591ml', 'price' => 1390, 'stock' => 30, 'cost' => 788, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://storage.googleapis.com/liquidos-public/products/large/1113029.png'],
            ['barcode' => '7801610001196', 'name' => 'Coca Cola Lata 350ml', 'price' => 1190, 'stock' => 60, 'cost' => 625, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQqptun4F9tEMB7uG-wbJEov9h9YCuRBhXPOw'],
            ['barcode' => '7801610001615', 'name' => 'Coca Cola 2L', 'price' => 2990, 'stock' => 20, 'cost' => 1367, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://images.lider.cl/wmtcl?source=url%5Bfile%3A%2Fproductos%2F278598ba.jpg%5D'],
            ['barcode' => '7801610323236', 'name' => 'Coca Cola Original 3L', 'price' => 3290, 'stock' => 18, 'cost' => 2243, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://storage.googleapis.com/liquidos-public/products/large/1110078.png'],
            ['barcode' => '7801610002858', 'name' => 'Fanta Naranja Lata 350ml', 'price' => 1190, 'stock' => 25, 'cost' => 625, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://storage.googleapis.com/liquidos-public/products/large/1110073.png'],
            ['barcode' => '7801610002926', 'name' => 'Fanta Naranja 591ml', 'price' => 1390, 'stock' => 30, 'cost' => 788, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://static.buale.cl/wp-content/uploads/2025/08/120636-Andina-Fanta-Naranja-Original-591ml.jpg'],
            ['barcode' => '7801610591994', 'name' => 'Sprite 591ml', 'price' => 1390, 'stock' => 25, 'cost' => 788, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://storage.googleapis.com/liquidos-public/products/large/1110041.png'],
            ['barcode' => '7801610005262', 'name' => 'Sprite 1.5L', 'price' => 2490, 'stock' => 18, 'cost' => 1366, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://clickwine.cl/cdn/shop/files/sprite-original-15l-desechable.png'],
            ['barcode' => '7801610211229', 'name' => 'Inca Kola Lata 350ml', 'price' => 1190, 'stock' => 25, 'cost' => 625, 'category' => 'Bebidas', 'supplier' => 'Coca Cola Andina', 'image' => 'https://micocacola.vteximg.com.br/arquivos/ids/196431-256-256/7801610276525_1.png'],
            ['barcode' => '7801505231912', 'name' => 'Azucar Iansa 1KG', 'price' => 2190, 'stock' => 15, 'cost' => 1214, 'category' => 'Despensa', 'supplier' => 'Mayvan', 'image' => 'https://encrypted-tbn3.gstatic.com/shopping?q=tbn:ANd9GcQHn5UqJUAXWdf6X-oLf1SFQX3Ob3mJgKB4pH8aJhPxVGGSXoTpX267cRb13wRAz7mrgVxfdGjlZC-O0WkHO45-HKkA2D2MuoqzDPFMLkfSfc8XK8adaMbR'],
            ['barcode' => '7702084137520', 'name' => 'Harina PAN 1kg', 'price' => 1590, 'stock' => 20, 'cost' => 966, 'category' => 'Despensa', 'supplier' => '58 Market', 'image' => 'https://media.falabella.com/tottusCL/20370246_1/w=800,h=800,fit=pad'],
            ['barcode' => '7509546000985', 'name' => 'Crema Dental Colgate 75ml', 'price' => 1190, 'stock' => 20, 'cost' => 361, 'category' => 'Despensa', 'supplier' => 'Santa Nao Spa', 'image' => 'https://perfumerialamundial.cl/cdn/shop/files/colgate-triple-accion-75-ml-menta_600x600.jpg'],
            ['barcode' => '7801900084540', 'name' => 'Salchicha Tradicional Winter 1kg', 'price' => 4690, 'stock' => 8, 'cost' => 2262, 'category' => 'Cecinas y Embutidos', 'supplier' => 'San Jorge', 'image' => 'https://i.bolder.run/r/czoyMzA1MyxnOjI2MHg/b437ce73/937292-sdjvkzjc.jpg'],
            ['barcode' => '7801907008402', 'name' => 'Vienesa Tradicional San Jorge 250Gr', 'price' => 1690, 'stock' => 15, 'cost' => 668, 'category' => 'Cecinas y Embutidos', 'supplier' => 'San Jorge', 'image' => 'https://images.rappi.cl/products/39b3dcfe-5707-49f3-97db-c14f01a3ad8f.png'],
            ['barcode' => '7801907004305', 'name' => 'Pate de Ternera 125gr San Jorge', 'price' => 890, 'stock' => 12, 'cost' => 485, 'category' => 'Cecinas y Embutidos', 'supplier' => 'San Jorge', 'image' => 'https://www.sanjorge.cl/wp-content/uploads/2019/12/430-SJ-Pate-Ternera-Jpn.jpg'],
            ['barcode' => '7801930008219', 'name' => 'Pate Receta del Abuelo 125gr', 'price' => 1190, 'stock' => 10, 'cost' => 699, 'category' => 'Cecinas y Embutidos', 'supplier' => 'Distribuidores', 'image' => 'https://images.lider.cl/wmtcl?source=url[file:/productos/CM656364.jpg]'],
            ['barcode' => '793969066247', 'name' => 'Pan de Perros x6', 'price' => 2790, 'stock' => 10, 'cost' => 1261, 'category' => 'Panaderia y Pasteleria', 'supplier' => 'Master Pan', 'image' => 'https://daghidelivery.com/cdn/shop/products/PANDEPERROSPEQUENOS_436e0997-afb1-41f2-9c3b-f5efab4669fc_1200x1200.jpg'],
            ['barcode' => '793969066254', 'name' => 'Pan de Hamburguesa x6', 'price' => 4190, 'stock' => 8, 'cost' => 1303, 'category' => 'Panaderia y Pasteleria', 'supplier' => 'Master Pan', 'image' => 'https://tiendaslistto.cl/erp/img/1757465260_PanHamburguesaBrioche12cm_4Unidades__11zon.webp'],
            ['barcode' => '2520690305285', 'name' => 'Muffin Chocolate 63gr', 'price' => 1290, 'stock' => 20, 'cost' => 638, 'category' => 'Panaderia y Pasteleria', 'supplier' => 'Breden Master', 'image' => 'https://media.istockphoto.com/id/183359376/es/foto/doble-magdalena-de-chocolate.jpg'],
            ['barcode' => '609143482416', 'name' => 'Butifarra Aji Verde 400g', 'price' => 4990, 'stock' => 5, 'cost' => 2363, 'category' => 'Cecinas y Embutidos', 'supplier' => 'Embutidos Diaz', 'image' => 'https://embutidosdiazchile.cl/wp-content/uploads/2024/02/PN-BUTIFARRA-AJI-VERDE.png'],
            ['barcode' => '707273568342', 'name' => 'Chorizo Ahumado 400g', 'price' => 4990, 'stock' => 6, 'cost' => 2363, 'category' => 'Cecinas y Embutidos', 'supplier' => 'Embutidos Diaz', 'image' => 'https://embutidosdiazchile.cl/wp-content/uploads/2024/02/ahumado.jpg'],
            ['barcode' => '764451223233', 'name' => 'Chorizo De Pollo 400g', 'price' => 4990, 'stock' => 8, 'cost' => 2110, 'category' => 'Cecinas y Embutidos', 'supplier' => 'Embutidos Diaz', 'image' => 'https://embutidosdiazchile.cl/wp-content/uploads/2024/02/PN-POLLO-AHUMADO.png'],
            ['barcode' => '3182550402439', 'name' => 'Puppy Royal Medium 1Kg', 'price' => 9990, 'stock' => 5, 'cost' => 5950, 'category' => 'Alimentos para Mascotas', 'supplier' => 'Comtech SpA', 'image' => 'https://tellevolasal.cl/web/image/product.template/884/image_1024'],
            ['barcode' => '7790187342231', 'name' => 'Puppy Royal Mini 1 Kg', 'price' => 9990, 'stock' => 5, 'cost' => 5950, 'category' => 'Alimentos para Mascotas', 'supplier' => 'Comtech SpA', 'image' => 'https://bestforpets.cl/tienda/8960-large_default/royal-canin-mini-puppy.jpg'],
            ['barcode' => '7798088562123', 'name' => 'Criadores Carne Adulto 1kg', 'price' => 3000, 'stock' => 10, 'cost' => 1500, 'category' => 'Alimentos para Mascotas', 'supplier' => 'Comtech SpA', 'image' => 'https://petfoodcartagena.cl/wp-content/uploads/2023/12/img_3106.jpeg'],
            ['barcode' => '7802000015779', 'name' => 'Cheetos Queso 110gr', 'price' => 1490, 'stock' => 15, 'cost' => 840, 'category' => 'Snacks', 'supplier' => 'Supermercados', 'image' => 'https://encrypted-tbn2.gstatic.com/shopping?q=tbn:ANd9GcSwaKQ65n_xvMWCzxpHGXd9YFAkTfyTquhnvL5DiUl3GAUC6bNTjYF_eO2Kq9_J-0hcwJosT_eEXgr5MvURhTFCHfGJbbqrQhIeYDseeVX-'],
            ['barcode' => '7802000016356', 'name' => 'Detodito Evercrisp 84gr', 'price' => 990, 'stock' => 12, 'cost' => 749, 'category' => 'Snacks', 'supplier' => 'Distribuidores', 'image' => 'https://media.falabella.com/tottusCL/80004724_1/w=800,h=800,fit=pad'],
            ['barcode' => '7802215502026', 'name' => 'Galleta Soda Costa 160gr', 'price' => 1290, 'stock' => 15, 'cost' => 845, 'category' => 'Chocolates y Galletas', 'supplier' => 'Supermercados', 'image' => 'https://unimarc.vtexassets.com/arquivos/ids/236523/000000000000666432-UN-01.jpg'],
            ['barcode' => '7802215107153', 'name' => 'Chocolate Costa Rama 60gr', 'price' => 1590, 'stock' => 15, 'cost' => 1075, 'category' => 'Chocolates y Galletas', 'supplier' => 'Distribuidores', 'image' => 'https://http2.mlstatic.com/D_NQ_NP_841722-MLA79857980006_102024-O.webp'],
            ['barcode' => '7802200130043', 'name' => 'Gomitas Mentita 25gr', 'price' => 390, 'stock' => 25, 'cost' => 155, 'category' => 'Dulces', 'supplier' => 'Distribuidores', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSf82caDRHFNDhROF1NStRpxqK73J759jJUcA'],
            ['barcode' => '7591446002176', 'name' => 'Maltin Polar Lata 355ml', 'price' => 1690, 'stock' => 15, 'cost' => 942, 'category' => 'Bebidas', 'supplier' => 'Global Ve', 'image' => 'https://cl.allofpan.com/wp-content/uploads/2025/05/lata_chile-1.png'],
            ['barcode' => '7591016854686', 'name' => 'Chocolate Mani Rikiti 30 Gr', 'price' => 1390, 'stock' => 18, 'cost' => 834, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://k21market.com/wp-content/uploads/2024/07/Chocolate-con-Leche-y-Mani-Rikiti-30g.jpg'],
            ['barcode' => '7591016854976', 'name' => 'Galak 30gr', 'price' => 1390, 'stock' => 20, 'cost' => 834, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQED1iCEb4uEsgqu6gUe0lA0kDU2FR47ZU56Kjeoi6DFrdNAE4GFSraZLV4mRFWlGXO5RUCPvXNpuv3G-IufKZZSTZ6hCqllOSVZIY1iV30y9RT61ovqmKav0SW'],
            ['barcode' => '7591016850305', 'name' => 'Carré Avellanas Savoy 25 gr', 'price' => 1390, 'stock' => 15, 'cost' => 769, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://cdnx.jumpseller.com/tu-super-tm/image/16018314/Dise_o_sin_t_tulo_-_2021-04-17T101440.576.png'],
            ['barcode' => '6998324875111', 'name' => 'Huevo Uniconcino 20 Gr', 'price' => 2390, 'stock' => 30, 'cost' => 1371, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://tiendaslistto.cl/erp/img/1757464881_249_f975aea5-ec74-4f02-a4b6-9a15ab9f7e23.webp'],
            ['barcode' => '6992468713216', 'name' => 'Gunys Acid Roll 60ml', 'price' => 1290, 'stock' => 15, 'cost' => 596, 'category' => 'Dulces', 'supplier' => 'Global Ve', 'image' => 'https://tamybrands.com/wp-content/uploads/2024/06/gunys-acid-rollon-2.jpg'],
            ['barcode' => '6995154852350', 'name' => 'Sour Beats Moco 23ml', 'price' => 790, 'stock' => 20, 'cost' => 357, 'category' => 'Dulces', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS4Ywnmdztc_Yd9nJp8WOfvrX6JxEFVfVGsY6ZxqH6W4eVElSx6qMhi5ja5t8ZQrKQUbFI'],
            ['barcode' => '7706642062026', 'name' => 'Tocineta Natural 23gr', 'price' => 990, 'stock' => 20, 'cost' => 361, 'category' => 'Snacks', 'supplier' => 'Global Ve', 'image' => 'https://www.confitiendas.com/wp-content/uploads/2022/03/Tocineta-Natural-26-g-PhotoRoom.png-PhotoRoom.png'],
            ['barcode' => '7706642003357', 'name' => 'Chicharron Express 18 Gr', 'price' => 1190, 'stock' => 15, 'cost' => 760, 'category' => 'Snacks', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn2.gstatic.com/shopping?q=tbn:ANd9GcT_cJC3dtkS4GovjplR4k8bKogwCxf-7zNFbsLZOkKcRmInL8W8P1lZmDA0CknPzOi1TPvBjlcufl5kkdqyV9eah_AjpbItag'],
            ['barcode' => '7709709293258', 'name' => 'Bocadillo Manjar 40 Gr', 'price' => 490, 'stock' => 15, 'cost' => 180, 'category' => 'Dulces', 'supplier' => 'Global Ve', 'image' => 'https://static.wixstatic.com/media/8ebfa2_e35b0561596d49feb7f16ff537e195d5~mv2.png/v1/fit/w_500,h_500,q_90/file.png'],
            ['barcode' => '7790040430433', 'name' => 'Alfajor Bon o Bon Chocolate', 'price' => 890, 'stock' => 12, 'cost' => 463, 'category' => 'Chocolates y Galletas', 'supplier' => 'Arcor', 'image' => 'https://images.lider.cl/wmtcl?source=url%5Bfile%3A%2Fproductos%2F263434a.jpg%5D'],
            ['barcode' => '7790040613607', 'name' => 'Alfajor Blanco Bon o Bon', 'price' => 890, 'stock' => 10, 'cost' => 483, 'category' => 'Chocolates y Galletas', 'supplier' => 'Arcor', 'image' => 'https://alvicl.vtexassets.com/arquivos/ids/156326/Alfajor-bon-o-bon-chocolate-blanco.jpg'],
            ['barcode' => '7790040613706', 'name' => 'Alfajor Leche BoB 40g', 'price' => 890, 'stock' => 12, 'cost' => 483, 'category' => 'Chocolates y Galletas', 'supplier' => 'Arcor', 'image' => 'https://i.bolder.run/r/czoyMzA1MyxnOjY5MHg/c3e52616/854959-Screenshot_18.png'],
            ['barcode' => '7790580423087', 'name' => 'Bigtime Nitro Mint 50g', 'price' => 3490, 'stock' => 10, 'cost' => 2445, 'category' => 'Dulces', 'supplier' => 'Arcor', 'image' => 'https://images.lider.cl/wmtcl?source=url[file:/productos/1325349a.jpg]'],
            ['barcode' => '4003084881608', 'name' => 'Trolli Gomitas 77,5 Gr', 'price' => 2490, 'stock' => 10, 'cost' => 1609, 'category' => 'Dulces', 'supplier' => 'Global Ve', 'image' => 'https://confiterialamundial.cl/wp-content/uploads/trolli-gomitas-lunch-bag-77gr.jpg.webp'],
            ['barcode' => '7791290794221', 'name' => 'CIF Crema Multiuso 750cc', 'price' => 2490, 'stock' => 10, 'cost' => 1219, 'category' => 'Limpieza', 'supplier' => 'Santa Nao Spa', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRNzbOdicMCJO308UBotJ8oeQfqLKJTzUv62Q'],
            ['barcode' => '7730219021338', 'name' => 'Jabon Elite Humectante 90gr', 'price' => 990, 'stock' => 20, 'cost' => 420, 'category' => 'Aseo personal', 'supplier' => 'Santa Nao Spa', 'image' => 'https://r.bolder.run/3846/large/803173-CPJAELI090.jpg'],
            ['barcode' => '7790250096061', 'name' => 'Toalla Ladysoft Ultra 8Un', 'price' => 1290, 'stock' => 25, 'cost' => 269, 'category' => 'Aseo personal', 'supplier' => 'Santa Nao Spa', 'image' => 'https://www.farmaciasahumada.cl/dw/image/v2/BJVH_PRD/on/demandware.static/-/Sites-ahumada-master-catalog/default/dw2570a3ac/images/products/49331/49331_2.jpg'],
            ['barcode' => '7702026145026', 'name' => 'Toalla Nosotras Nocturna 8un', 'price' => 1590, 'stock' => 15, 'cost' => 627, 'category' => 'Aseo personal', 'supplier' => 'Santa Nao Spa', 'image' => 'https://http2.mlstatic.com/D_NQ_NP_910644-MLC71926189534_092023-O.webp'],
            ['barcode' => '4891228530136', 'name' => 'Afeitadora Schick Titanium 4H', 'price' => 1290, 'stock' => 10, 'cost' => 856, 'category' => 'Aseo personal', 'supplier' => 'Santa Nao Spa', 'image' => 'https://r.bolder.run/3846/large/818806-CPFLSCH132.jpg'],
            ['barcode' => '7509546046822', 'name' => 'Desodorante Speed Stick 50gr', 'price' => 3490, 'stock' => 8, 'cost' => 2249, 'category' => 'Aseo personal', 'supplier' => 'Distribuidores', 'image' => 'https://unimarc.vteximg.com.br/arquivos/ids/231259-256-256/000000000000558084-UN-01.jpg'],
            ['barcode' => '7500435138000', 'name' => 'Shampoo H&S Carbón 375ml', 'price' => 5290, 'stock' => 5, 'cost' => 3273, 'category' => 'Aseo personal', 'supplier' => 'Santa Nao Spa', 'image' => 'https://r.bolder.run/3846/medium/916556-CPSHHYS916.jpg'],
            ['barcode' => '7500435155847', 'name' => 'Shampoo Pantene Bambu 400ML', 'price' => 4890, 'stock' => 5, 'cost' => 2890, 'category' => 'Aseo personal', 'supplier' => 'Distribuidores', 'image' => 'https://www.maicao.cl/dw/image/v2/BDPM_PRD/on/demandware.static/-/Sites-masterCatalog_Chile/default/dwc4643f0e/images/large/386516-shampoo-control-caida-bambu-nutre-y-crece-400-ml.jpg'],
            ['barcode' => '7500435191425', 'name' => 'Shampoo Pantene Colageno 300ml', 'price' => 4890, 'stock' => 5, 'cost' => 2890, 'category' => 'Aseo personal', 'supplier' => 'Distribuidores', 'image' => 'https://www.farmaciasahumada.cl/dw/image/v2/BJVH_PRD/on/demandware.static/-/Sites-ahumada-master-catalog/default/dwa0e0b3e1/images/products/90934/90934.jpg'],
            ['barcode' => '7591016201244', 'name' => 'Cerelac Bolsa 900 Gr', 'price' => 11990, 'stock' => 8, 'cost' => 8721, 'category' => 'Alimentos', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn3.gstatic.com/shopping?q=tbn:ANd9GcQN9vsI_4Mn9SIEyK_gHMOM4x9rW1QkXkMj8uKPtj38FLa-ulqge51u9k74zpnEOh6xjGvzVI935g4LwVSJJTYAbOtjUg3kPn42demdhrcU2eIsXytlyCO3Kw'],
            ['barcode' => '7591016203729', 'name' => 'Cerelac Bolsa 400gr', 'price' => 7290, 'stock' => 10, 'cost' => 4922, 'category' => 'Alimentos', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn1.gstatic.com/shopping?q=tbn:ANd9GcQNvuTpC6xTZ9PZ4X623BXGbNE0slUj9upOXCU3gRR3UHWuIVfq1ZR8uJwd6gZzwkMrGOGAc0XZpNHn0ogrGKcVpqGck7usMhQ9sjdJozKmMjxB684tXwK4'],
            ['barcode' => '766871813346', 'name' => 'El Chichero 300 ml', 'price' => 2190, 'stock' => 8, 'cost' => 1411, 'category' => 'Lacteos', 'supplier' => 'Global Ve', 'image' => 'https://www.caretrading.cl/wp-content/uploads/2022/07/chicha-330-cc-900x1000-1.png'],
            ['barcode' => '766871813377', 'name' => 'Rikomalt 1 Litro', 'price' => 3990, 'stock' => 10, 'cost' => 2869, 'category' => 'Lacteos', 'supplier' => 'Global Ve', 'image' => 'https://i5.walmartimages.cl/asr/e5bc2c00-7eac-4b45-b6c3-1b53f786b20b.27c4df4adfa3837b8cba750495a530c6.jpeg'],
            ['barcode' => '083322063724', 'name' => 'Avena Alpina Original 200 Ml', 'price' => 1290, 'stock' => 18, 'cost' => 798, 'category' => 'Lacteos', 'supplier' => 'Global Ve', 'image' => 'https://alpinaus.com/cdn/shop/files/Avena-Alpina-Original-200ml.jpg'],
            ['barcode' => '83322063731', 'name' => 'Avena Alpina Canela 200 Ml', 'price' => 1190, 'stock' => 20, 'cost' => 798, 'category' => 'Lacteos', 'supplier' => 'Global Ve', 'image' => 'https://barberiinternational.com/wp-content/uploads/2019/01/Avena-Alpina-Cinnamon-200ml.jpg'],
            ['barcode' => '83322082015', 'name' => 'Dulce De Leche Alpina 50gr', 'price' => 1190, 'stock' => 25, 'cost' => 630, 'category' => 'Dulces', 'supplier' => 'Global Ve', 'image' => 'https://tiendaslistto.cl/erp/img/1757464162_images (6).jpg'],
            ['barcode' => '41331028516', 'name' => 'Chiles Jalapeños Goya 312 Gr', 'price' => 3190, 'stock' => 8, 'cost' => 2205, 'category' => 'Despensa', 'supplier' => 'Global Ve', 'image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQlyuhwp4Ft051RD5xt9R9Kic1EL6gG8eYPvQ'],
            ['barcode' => '41331049436', 'name' => 'Galletas Maria Goya 200 Gr', 'price' => 2190, 'stock' => 8, 'cost' => 1504, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://saboresdelcaribe.cl/wp-content/uploads/galletas-maria-goya-tradicional2.jpg'],
            ['barcode' => '41331050104', 'name' => 'Galletas Wafer Coconut 140 Gr', 'price' => 1990, 'stock' => 10, 'cost' => 1365, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://goya.es/storage/productos/5010-wafer-de-coco-1000x1000.webp'],
            ['barcode' => '41331064316', 'name' => 'Goya Galletas Carnaval 33.6 Gr', 'price' => 490, 'stock' => 15, 'cost' => 250, 'category' => 'Chocolates y Galletas', 'supplier' => 'Global Ve', 'image' => 'https://img1.elyerromenu.com/images/donraul-store/goya-carnaval-vainilla-33-6g/img.webp'],
            ['barcode' => '633148100099', 'name' => 'Tajin Clasico Chico 45 Gr', 'price' => 3590, 'stock' => 8, 'cost' => 2280, 'category' => 'Despensa', 'supplier' => 'Global Ve', 'image' => 'https://tiendaslistto.cl/erp/img/1757464335_w=800,h=800,fit=pad (1).webp'],
            ['barcode' => '736372665485', 'name' => 'Bebida Papelon Limon 500 Ml', 'price' => 1490, 'stock' => 10, 'cost' => 995, 'category' => 'Bebidas', 'supplier' => 'Global Ve', 'image' => 'https://static.wixstatic.com/media/8ebfa2_626059c503084efaa829f801eea2fe8a~mv2.png/v1/fill/w_480,h_480,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/8ebfa2_626059c503084efaa829f801eea2fe8a~mv2.png'],
            ['barcode' => '745853628505', 'name' => 'Servilletas Bids 300Un', 'price' => 1390, 'stock' => 20, 'cost' => 664, 'category' => 'Limpieza', 'supplier' => 'Santa Nao Spa', 'image' => 'https://http2.mlstatic.com/D_Q_NP_2X_788238-MLC89586039344_082025-T-servilletas-cocktail-mesa-300un-paquete-servilletas-300-un.webp'],
            ['barcode' => '7802095000889', 'name' => 'Torilla Pack Pancho Villa 600gr', 'price' => 3690, 'stock' => 5, 'cost' => 2350, 'category' => 'Panaderia y Pasteleria', 'supplier' => 'Distribuidores', 'image' => 'https://tiendaslistto.cl/erp/img/1757465622_1010090-7802095000889.JPG'],
            ['barcode' => '7802095000209', 'name' => 'Quesavilla Pancho Villa 200gr', 'price' => 1390, 'stock' => 8, 'cost' => 951, 'category' => 'Alimentos', 'supplier' => 'Distribuidores', 'image' => 'https://12tren.com/wp-content/uploads/2023/04/PANCHO-VILLA-QUESAVILLA-200GR-1.jpg'],
        ];

        $count = 0;
        foreach ($products as $product) {
            $categoryId = $categories[$product['category']] ?? null;
            $supplierId = $suppliers[$product['supplier']] ?? null;

            Product::updateOrCreate(
                ['barcode' => $product['barcode'], 'tenant_id' => $tenant->id],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'cost' => $product['cost'],
                    'category_id' => $categoryId,
                    'supplier_id' => $supplierId,
                    'image' => $product['image'],
                    'is_active' => true,
                    'is_archived' => false,
                ]
            );
            $count++;
        }

        $this->command->info('ProductSeeder: ' . $count . ' products seeded.');
    }
}
