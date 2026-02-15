@if(setting('moniq_enable_chat') && setting('moniq_public_key'))
<!-- Komposa Chat Widget -->
<script>
    (function(w,d,s,o,f,js,fjs){
        w['KomposaChat']=o;w[o]=w[o]||function(){(w[o].q=w[o].q||[]).push(arguments)};
        js=d.createElement(s),fjs=d.getElementsByTagName(s)[0];
        js.id=o;js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
    }(window,document,'script','komposa','https://www.komposa.com/chat.js'));
    komposa('init', {
        publicKey: '{{ setting('moniq_public_key') }}',
        title: '{{ setting('store_name') }} Support',
        greeting: 'Hi! How can we help you today?'
    });
</script>
@endif
