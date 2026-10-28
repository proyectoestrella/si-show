/* Planes: desplegar la comparativa completa y recordar desde qué plan se pide la reunión. */
(()=>{"use strict";
const section=document.getElementById("planes"),button=document.getElementById("plans-more");
if(section&&button){
  const label=button.querySelector(".plans-more-label"),count=button.querySelector(".plans-more-count");
  button.addEventListener("click",()=>{
    const open=!section.classList.contains("is-expanded");
    section.classList.toggle("is-expanded",open);
    button.setAttribute("aria-expanded",String(open));
    label.textContent=open?"Ocultar la comparativa completa":"Ver todas las funcionalidades";
    count.hidden=open;
    if(!open){const r=button.getBoundingClientRect();if(r.top<0||r.bottom>innerHeight)button.scrollIntoView({block:"center"});}
  });
}
const message=document.getElementById("meeting-message");let auto="";
document.querySelectorAll(".plan-cta[data-plan]").forEach(a=>a.addEventListener("click",()=>{
  if(!message)return;
  const text=`Me interesa el plan ${a.dataset.plan}.`;
  if(!message.value.trim()||message.value===auto){message.value=text;auto=text;message.dispatchEvent(new Event("input",{bubbles:true}));}
}));
})();
