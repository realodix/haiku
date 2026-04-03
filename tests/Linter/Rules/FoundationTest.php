<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class FoundationTest extends TestCase
{
    #[PHPUnit\Test]
    public function test_1(): void
    {
        $lines = [
            '*$_____',
            '/ads.$doc,to=example.com,reason="foo, bar"',
            '||cdn.edipresse.pl/player/wizaz/player.min.js$replace=/(appState\.status\.floating)=!0/\$1=!1/',
            '||giphy.com^$replace=/"htlAds\\":\[\\".{1\,5}\\".*?\]/"htlAds\\":\[\]/,document',
            '$script,domain=example.com,jsonprune=\$..[direct\,"rtbAuctionInfo"\, "blockId"\, "linkTail"\, "seatbid"]',
            '@@||apis.quantcast.mgr.consensu.org/CookieAccess$domain=blitz.gg,app=Blitz.exe',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function test_2(): void
    {
        $lines = [
            '##.ads',
            '$$advertisement-module',
            'example.com$$advertisement-module',
            '[$path=/app/consent]volksfreund.de#%#//scriptlet(\'trusted-click-element\', \'#consentAccept\', \'\', \'500\')',
            '[$path=/search]ya.ru,yandex.*##.AdvOffers',
            // is to much
            '*##._popIn_recommend_article_ad',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function opt_replace(): void
    {
        $lines = [
            '*$frame,3p,replace=/adBlockDetected//,from=filemoon.*',

            '$frame,3p,domain=8rm3l0i9.fun|iqksisgw.xyz|u6lyxl0w.skin|l1afav.net,replace=\'/^/<script>(()=>{window.open=new Proxy(window.open,{apply:(e,t,r)=>{}});const e=new WeakMap,t=(e,t)=>{try{e.dispatchEvent(new Event(t))}catch{}};XMLHttpRequest.prototype;self.XMLHttpRequest=class extends self.XMLHttpRequest{open(t,r,...n){if(e.delete(this),new URL(r).hostname.endsWith(".cdn255.com"))return super.open(t,r,...n);const s={method:t,url:r},a=Object.assign(s,{xhr:this,headers:{date:"","content-type":"","content-length":""},url:s.url,props:{response:{value:""},responseText:{value:""},responseXML:{value:null}}});return e.set(this,a),super.open(t,r,...n)}send(...r){const n=e.get(this);return void 0===n?super.send(...r):!1===n.defer?(n.headers["content-length"]=`${n.props.response.value}`.length,Object.defineProperties(n.xhr,{readyState:{value:4},responseURL:{value:n.url},status:{value:200},statusText:{value:"OK"}}),void Object.defineProperties(n.xhr,n.props)):void Promise.resolve("").then((()=>n)).then((e=>(Object.defineProperties(e.xhr,{readyState:{value:1,configurable:!0},responseURL:{value:n.url}}),t(e.xhr,"readystatechange"),e))).then((e=>(n.headers["content-length"]=`${e.props.response.value}`.length,Object.defineProperties(e.xhr,{readyState:{value:2,configurable:!0},status:{value:200},statusText:{value:"OK"}}),t(e.xhr,"readystatechange"),e))).then((e=>(Object.defineProperties(e.xhr,{readyState:{value:3,configurable:!0}}),Object.defineProperties(e.xhr,e.props),t(e.xhr,"readystatechange"),e))).then((e=>{Object.defineProperties(e.xhr,{readyState:{value:4}}),t(e.xhr,"readystatechange"),t(e.xhr,"load"),t(e.xhr,"loadend")}))}};let r=document.querySelector("script");r.innerHTML.includes("window.open")&&r.parentElement.removeChild(r)})();<\/script>/\'',

            '/^https:\/\/[a-z0-9]{2,}-[a-z0-9]{8}\.(?:com|nl)\/[a-z0-9-]+/[a-z0-9]{12}\b/$frame,3p,match-case,to=com|nl,ipaddress=/^(1(72\.67\.\d{3}|04\.21\.\d+)\.\d+|188\.114\.9[67]\.[08]|64:ff9b::[a-f0-9]{4}:[a-f0-9]{1,4})$/,replace=\'/^/<script>(()=>{window.open=new Proxy(window.open,{apply:(n,o,w)=>{}});let e=document.querySelector("script");e.innerHTML.includes("window.open")&&e.parentElement.removeChild(e)})();<\/script>/i\'',

            '/^https:\/\/[a-z0-9]{8}\.com\/[a-z0-9-]+\/[a-zA-Z0-9]{12,50}\b/$frame,1p,strict1p,match-case,to=com,ipaddress=/^(1(72\.67\.\d{3}|04\.21\.\d+)\.\d+|188\.114\.9[67]\.[08]|64:ff9b::[a-f0-9]{4}:[a-f0-9]{1,4})$/,replace=\'/^/<script>(()=>{window.open=new Proxy(window.open,{apply:(n,o,w)=>{}});let e=document.querySelector("script");e.innerHTML.includes("window.open")&&e.parentElement.removeChild(e)})();<\/script>/i',

            '/^https:\/\/[a-z0-9]{4,8}\.[a-z]{2,5}\/[a-z0-9-]+\/[a-zA-Z0-9]{12,50}\b/$frame,3p,match-case,to=~net|~org|~gov|~edu|~br|~jp|~ir|~it|~ru,ipaddress=/^(1(72\.67\.\d{3}|04\.21\.\d+)\.\d+|188\.114\.9[67]\.[08]|64:ff9b::[a-f0-9]{4}:[a-f0-9]{1,4})$/,replace=\'/^/<script>(()=>{window.open=new Proxy(window.open,{apply:(n,o,w)=>{}});let e=document.querySelector("script");e.innerHTML.includes("window.open")&&e.parentElement.removeChild(e)})();<\/script>/i\'',
        ];

        $this->analyse($lines);
    }
}
