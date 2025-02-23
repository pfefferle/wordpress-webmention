(()=>{"use strict";const e=window.React,n=window.wp.editor,t=window.wp.plugins,o=window.wp.components,i=window.wp.data,w=window.wp.coreData,s=window.wp.i18n;(0,t.registerPlugin)("webmention-editor-plugin",{render:()=>{const t=(0,i.useSelect)((e=>e("core/editor").getCurrentPostType()),[]),[r,c]=(0,w.useEntityProp)("postType",t,"meta");return(0,e.createElement)(n.PluginDocumentSettingPanel,{name:"webmention",title:(0,s.__)("Webmentions","webmention")},(0,e.createElement)(o.CheckboxControl,{__nextHasNoMarginBottom:!0,label:(0,s.__)("Disable Webmentions","webmention"),help:(0,s.__)("Do not accept incoming Webmentions for this post.","webmention"),checked:r.webmentions_closed,onChange:e=>{c({...r,webmentions_closed:e})}}))}})})();