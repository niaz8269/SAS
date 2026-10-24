{{-- tawk.to live chat. Only an https://embed.tawk.to/... address is accepted, so a wrong setting cannot load some other script. --}}
@if (str_starts_with((string) config('sas.tawk_embed'), 'https://embed.tawk.to/'))
<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src={!! json_encode(config('sas.tawk_embed'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!};
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->
@endif
