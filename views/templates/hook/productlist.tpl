<section class="featured-products">
    <h2>{$category} {l s='Product List'}</h2>
    <div class="products">
        {foreach from=$products item="product"}
            {include file="catalog/_partials/miniatures/product.tpl" product=$product}
        {/foreach}
    </div>
</section>
