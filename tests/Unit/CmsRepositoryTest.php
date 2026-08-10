<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Repositories\CmsRepository;
use OEMS\Tests\Support\TestCase;
use PDO;

final class CmsRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CmsRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,slug TEXT UNIQUE,content TEXT,meta_title TEXT,meta_description TEXT,status TEXT,published_at TEXT,created_by INTEGER,updated_by INTEGER,created_at TEXT,updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE faqs (id INTEGER PRIMARY KEY AUTOINCREMENT,question TEXT,answer TEXT,category TEXT,sort_order INTEGER,is_active INTEGER,created_at TEXT,updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE banners (id INTEGER PRIMARY KEY AUTOINCREMENT,title TEXT,subtitle TEXT,image_path TEXT,link_url TEXT,location TEXT,starts_at TEXT,ends_at TEXT,is_active INTEGER,sort_order INTEGER,created_at TEXT,updated_at TEXT)');
        $this->pdo->exec("INSERT INTO pages VALUES (1,'About','about','About copy',NULL,NULL,'published','2026-08-10 08:00:00',NULL,NULL,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,'Contact','contact','Contact copy',NULL,NULL,'draft',NULL,NULL,NULL,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $this->pdo->exec("INSERT INTO faqs VALUES (1,'Later','Answer','General',20,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,'First','Answer','General',10,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(3,'Hidden','Answer',NULL,1,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $this->pdo->exec("INSERT INTO banners VALUES (1,'Current',NULL,'/uploads/banners/a.jpg','/events','home','2026-08-10 09:00:00','2026-08-10 13:00:00',1,20,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(2,'Future',NULL,'/uploads/banners/b.jpg',NULL,'home','2026-08-11 09:00:00',NULL,1,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP),(3,'Other',NULL,'/uploads/banners/c.jpg',NULL,'event',NULL,NULL,1,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $this->repository = new CmsRepository($this->pdo);
    }

    public function testPublishedPagesFaqsAndCurrentBannersAreScopedAndOrdered(): void
    {
        $this->assertNotNull($this->repository->findPage('about', true));
        $this->assertNull($this->repository->findPage('contact', true));
        $this->assertSame(['First', 'Later'], array_column($this->repository->activeFaqs(), 'question'));
        $this->assertSame(['Current'], array_column($this->repository->activeHomeBanners('2026-08-10 12:00:00'), 'title'));
    }

    public function testWritesPreserveFixedPageIdentityAndManageFaqAndBannerStatus(): void
    {
        $this->assertTrue($this->repository->updatePage('about', ['title' => 'About OEMS', 'content' => 'Updated', 'meta_title' => null, 'meta_description' => null], 9));
        $this->assertSame('about', $this->repository->findPage('about')['slug']);
        $this->assertTrue($this->repository->setPagePublished('contact', true, 9));
        $faqId = $this->repository->createFaq(['question' => 'New?', 'answer' => 'Yes.', 'category' => null, 'sort_order' => 5]);
        $this->assertTrue($this->repository->setFaqActive($faqId, false));
        $bannerId = $this->repository->createBanner(['title' => 'New banner', 'subtitle' => null, 'image_path' => '/uploads/banners/new.jpg', 'link_url' => '/', 'location' => 'home', 'starts_at' => null, 'ends_at' => null, 'sort_order' => 3]);
        $this->assertTrue($this->repository->setBannerActive($bannerId, false));
    }
}
