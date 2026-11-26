<?php
declare(strict_types=1);

use function Bookstore\Config\bootstrap;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';

bootstrap();

$pageTitle = 'About us';
$searchQuery = '';
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>
<main id="main-content" class="about-page">
  <section class="about-hero">
    <div>
      <span class="eyebrow">ABOUT THE READING ROOM</span>
      <h1>Books bring us closer to ideas, people, and possibility.</h1>
    </div>
    <p>We are building a thoughtful place for Ghana’s readers to discover stories, knowledge, and the books that stay with them.</p>
  </section>

  <section class="about-section">
    <article>
      <span class="about-number">01</span>
      <div>
        <span class="eyebrow">WHO WE ARE</span>
        <h2>Passionate about every reading journey</h2>
        <p>At The Reading Room, we're passionate about connecting readers with the books they love. From bestselling fiction and timeless classics to educational and professional resources, our platform is designed to make book discovery simple, enjoyable, and accessible for everyone.</p>
      </div>
    </article>

    <article>
      <span class="about-number">02</span>
      <div>
        <span class="eyebrow">OUR STORY</span>
        <h2>Created around a shared love of books</h2>
        <p>The Reading Room was created with one goal in mind: to build a convenient online space where book lovers can explore, discover, and purchase their next great read. What started as a love for books has grown into a trusted destination for readers of all interests and ages.</p>
      </div>
    </article>

    <article>
      <span class="about-number">03</span>
      <div>
        <span class="eyebrow">OUR VISION</span>
        <h2>Making knowledge easier to reach</h2>
        <p>We envision a world where every reader can easily access knowledge, inspiration, and entertainment through books. Our mission is to foster a community of lifelong learners by making quality books available to everyone, anytime and anywhere.</p>
      </div>
    </article>
  </section>

  <section class="about-team">
    <div>
      <span class="eyebrow">MEET THE TEAM</span>
      <h2>People behind the pages</h2>
    </div>
    <p>Behind The Reading Room is a dedicated team of book enthusiasts, technology professionals, and customer service specialists committed to delivering an exceptional reading and shopping experience. Together, we work to bring the best books directly to our readers.</p>
  </section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
