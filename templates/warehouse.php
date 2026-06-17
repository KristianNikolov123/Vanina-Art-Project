<?php include __DIR__ . '/partials/warehouse_nav.php'; ?>

<div class="row g-4">
    <div class="col-md-4">
        <a href="<?= url_for('profiles') ?>" class="card text-decoration-none h-100 warehouse-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-border-all text-primary"></i> Профили</h5>
                <p class="card-text text-muted">Материал (€/л.м.) и ширина на профила за изчисление на труд.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url_for('glasses') ?>" class="card text-decoration-none h-100 warehouse-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-window-maximize text-primary"></i> Стъкла</h5>
                <p class="card-text text-muted">Цени по ценоразпис — €/кв.м. с минимум на брой.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url_for('passepartouts') ?>" class="card text-decoration-none h-100 warehouse-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-image text-primary"></i> Паспарту</h5>
                <p class="card-text text-muted">Материал по листове; рязане се начислява автоматично.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url_for('backs') ?>" class="card text-decoration-none h-100 warehouse-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-layer-group text-primary"></i> Гръбове</h5>
                <p class="card-text text-muted">Велпапе, картон, пенокартон, фазер — €/кв.м.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url_for('hanging') ?>" class="card text-decoration-none h-100 warehouse-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-link text-primary"></i> Окачване</h5>
                <p class="card-text text-muted">Закачалки, връзки и друго оборудване за окачване.</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= url_for('services') ?>" class="card text-decoration-none h-100 warehouse-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-tools text-primary"></i> Услуги и труд</h5>
                <p class="card-text text-muted">Фиксирани тарифи от ценоразписа — изработка, рязане и др.</p>
            </div>
        </a>
    </div>
</div>

<style>
.warehouse-card { transition: box-shadow .2s, transform .2s; border: 1px solid #dee2e6; }
.warehouse-card:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.1); transform: translateY(-2px); }
.warehouse-nav .nav-link { color: #495057; }
.warehouse-nav .nav-link.active { background: #0d6efd; }
</style>
