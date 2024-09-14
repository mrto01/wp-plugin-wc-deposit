(()=>{"use strict";var e,t={102:()=>{const e=window.React,{ExperimentalOrderMeta:t}=(window.wp.i18n,wc.blocksCheckout),{FormattedMonetaryAmount:a}=wc.blocksComponents,o=wc.priceFormat.getCurrency(),r=({cart:t,extensions:r,context:l})=>{if(r.hasOwnProperty("depositData")&&r.depositData.future_payment>0&&r.depositData.deposit_amount>0){const t={};return t.depositAmount=r.depositData.deposit_amount,t.futurePayment=r.depositData.future_payment,t.feeTotal=r.depositData.fee_total,t.depositFee=r.depositData.deposit_fee,t.showFees=r.depositData.show_fees,t.depositLabel=r.depositData.deposit_text,t.futurePaymentsLabel=r.depositData.future_payments_text,t.feesLabel=r.depositData.fees_text,o.prefix=o.symbol+" ",(0,e.createElement)("div",{className:"vi-block-deposit-wrapper"},(0,e.createElement)("div",{className:"vi-block-deposit-item wc-block-components-totals-item"},(0,e.createElement)("span",{className:"vi-block-deposit-item__label"},t.depositLabel),(0,e.createElement)("span",{className:"vi-block-deposit-item__value"},(0,e.createElement)(a,{currency:o||{},value:t.depositAmount}),(0,e.createElement)(e.Fragment,null," "),(0,e.createElement)(s,{depositData:t,context:"deposit"}))),(0,e.createElement)("div",{className:"vi-block-deposit-item wc-block-components-totals-item"},(0,e.createElement)("span",{className:"vi-block-deposit-item__label"},t.futurePaymentsLabel),(0,e.createElement)("div",{className:"vi-block-deposit-item__value"},(0,e.createElement)(a,{currency:o||{},value:t.futurePayment}),(0,e.createElement)(e.Fragment,null," "),(0,e.createElement)(s,{depositData:t,context:"future_payment"}))))}return(0,e.createElement)(e.Fragment,null)},s=({depositData:t,context:r})=>{if(0==t.showFees)return(0,e.createElement)(e.Fragment,null);{o.prefix=o.symbol+" ";let s=0;if(s="deposit"===r?t.depositFee:t.feeTotal-t.depositFee,s>0)return(0,e.createElement)(e.Fragment,null,(0,e.createElement)("small",{className:"vi-block-fee"},"(+ ",(0,e.createElement)(a,{currency:o||{},value:s})," ",t.feesLabel,")"))}},{registerPlugin:l}=wp.plugins;l("depart-deposit-and-part-payment-for-woocommerce",{render:()=>(0,e.createElement)(t,null,(0,e.createElement)(r,null)),scope:"woocommerce-checkout"})}},a={};function o(e){var r=a[e];if(void 0!==r)return r.exports;var s=a[e]={exports:{}};return t[e](s,s.exports,o),s.exports}o.m=t,e=[],o.O=(t,a,r,s)=>{if(!a){var l=1/0;for(m=0;m<e.length;m++){for(var[a,r,s]=e[m],n=!0,i=0;i<a.length;i++)(!1&s||l>=s)&&Object.keys(o.O).every((e=>o.O[e](a[i])))?a.splice(i--,1):(n=!1,s<l&&(l=s));if(n){e.splice(m--,1);var c=r();void 0!==c&&(t=c)}}return t}s=s||0;for(var m=e.length;m>0&&e[m-1][2]>s;m--)e[m]=e[m-1];e[m]=[a,r,s]},o.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={57:0,350:0};o.O.j=t=>0===e[t];var t=(t,a)=>{var r,s,[l,n,i]=a,c=0;if(l.some((t=>0!==e[t]))){for(r in n)o.o(n,r)&&(o.m[r]=n[r]);if(i)var m=i(o)}for(t&&t(a);c<l.length;c++)s=l[c],o.o(e,s)&&e[s]&&e[s][0](),e[s]=0;return o.O(m)},a=globalThis.webpackChunkdeposit_summary_block=globalThis.webpackChunkdeposit_summary_block||[];a.forEach(t.bind(null,0)),a.push=t.bind(null,a.push.bind(a))})();var r=o.O(void 0,[350],(()=>o(102)));r=o.O(r)})();