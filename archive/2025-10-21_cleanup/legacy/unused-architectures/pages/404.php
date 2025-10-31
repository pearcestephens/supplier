<?php
/**
 * 404 Not Found Page
 * 
 * AdminLTE styled error page
 */
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0">Page Not Found</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="error-page">
            <h2 class="headline text-warning"> 404</h2>

            <div class="error-content">
                <h3><i class="fas fa-exclamation-triangle text-warning"></i> Oops! Page not found.</h3>

                <p>
                    We could not find the page you were looking for.
                    Meanwhile, you may <a href="?page=dashboard">return to dashboard</a> or try using the search form.
                </p>

                <form class="search-form" method="GET" action="">
                    <input type="hidden" name="page" value="purchase-orders">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search...">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<style>
.error-page {
    width: 600px;
    margin: 20px auto 0;
}

.error-page > .headline {
    float: left;
    font-size: 100px;
    font-weight: 300;
}

.error-page > .error-content {
    margin-left: 190px;
    display: block;
}

.error-page > .error-content > h3 {
    font-weight: 300;
    font-size: 25px;
}

@media (max-width: 767px) {
    .error-page {
        width: 100%;
    }
    
    .error-page > .headline {
        float: none;
        text-align: center;
    }
    
    .error-page > .error-content {
        margin-left: 0;
    }
}
</style>
