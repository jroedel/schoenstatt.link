(function(expose){var Markdown=expose.Markdown=function Markdown(dialect){switch(typeof dialect){case"undefined":this.dialect=Markdown.dialects.Gruber;break;case"object":this.dialect=dialect;break;default:if(dialect in Markdown.dialects){this.dialect=Markdown.dialects[dialect];}
else{throw new Error("Unknown Markdown dialect '"+String(dialect)+"'");}
break;}
this.em_state=[];this.strong_state=[];this.debug_indent="";};expose.parse=function(source,dialect){var md=new Markdown(dialect);return md.toTree(source);};expose.toHTML=function toHTML(source,dialect,options){var input=expose.toHTMLTree(source,dialect,options);return expose.renderJsonML(input);};expose.toHTMLTree=function toHTMLTree(input,dialect,options){if(typeof input==="string")input=this.parse(input,dialect);var attrs=extract_attr(input),refs={};if(attrs&&attrs.references){refs=attrs.references;}
var html=convert_tree_to_html(input,refs,options);merge_text_nodes(html);return html;};function mk_block_toSource(){return"Markdown.mk_block( "+
uneval(this.toString())+", "+
uneval(this.trailing)+", "+
uneval(this.lineNumber)+" )";}
function mk_block_inspect(){var util=require('util');return"Markdown.mk_block( "+
util.inspect(this.toString())+", "+
util.inspect(this.trailing)+", "+
util.inspect(this.lineNumber)+" )";}
var mk_block=Markdown.mk_block=function(block,trail,line){if(arguments.length==1)trail="\n\n";var s=new String(block);s.trailing=trail;s.inspect=mk_block_inspect;s.toSource=mk_block_toSource;if(line!=undefined)
s.lineNumber=line;return s;};function count_lines(str){var n=0,i=-1;while((i=str.indexOf('\n',i+1))!==-1)n++;return n;}
Markdown.prototype.split_blocks=function splitBlocks(input,startLine){var re=/([\s\S]+?)($|\n(?:\s*\n|$)+)/g,blocks=[],m;var line_no=1;if((m=/^(\s*\n)/.exec(input))!=null){line_no+=count_lines(m[0]);re.lastIndex=m[0].length;}
while((m=re.exec(input))!==null){blocks.push(mk_block(m[1],m[2],line_no));line_no+=count_lines(m[0]);}
return blocks;};Markdown.prototype.processBlock=function processBlock(block,next){var cbs=this.dialect.block,ord=cbs.__order__;if("__call__"in cbs){return cbs.__call__.call(this,block,next);}
for(var i=0;i<ord.length;i++){var res=cbs[ord[i]].call(this,block,next);if(res){if(!isArray(res)||(res.length>0&&!(isArray(res[0]))))
this.debug(ord[i],"didn't return a proper array");return res;}}
return[];};Markdown.prototype.processInline=function processInline(block){return this.dialect.inline.__call__.call(this,String(block));};Markdown.prototype.toTree=function toTree(source,custom_root){var blocks=source instanceof Array?source:this.split_blocks(source);var old_tree=this.tree;try{this.tree=custom_root||this.tree||["markdown"];blocks:while(blocks.length){var b=this.processBlock(blocks.shift(),blocks);if(!b.length)continue blocks;this.tree.push.apply(this.tree,b);}
return this.tree;}
finally{if(custom_root){this.tree=old_tree;}}};Markdown.prototype.debug=function(){var args=Array.prototype.slice.call(arguments);args.unshift(this.debug_indent);if(typeof print!=="undefined")
print.apply(print,args);if(typeof console!=="undefined"&&typeof console.log!=="undefined")
console.log.apply(null,args);}
Markdown.prototype.loop_re_over_block=function(re,block,cb){var m,b=block.valueOf();while(b.length&&(m=re.exec(b))!=null){b=b.substr(m[0].length);cb.call(this,m);}
return b;};Markdown.dialects={};Markdown.dialects.Gruber={block:{atxHeader:function atxHeader(block,next){var m=block.match(/^(#{1,6})\s*(.*?)\s*#*\s*(?:\n|$)/);if(!m)return undefined;var header=["header",{level:m[1].length}];Array.prototype.push.apply(header,this.processInline(m[2]));if(m[0].length<block.length)
next.unshift(mk_block(block.substr(m[0].length),block.trailing,block.lineNumber+2));return[header];},setextHeader:function setextHeader(block,next){var m=block.match(/^(.*)\n([-=])\2\2+(?:\n|$)/);if(!m)return undefined;var level=(m[2]==="=")?1:2;var header=["header",{level:level},m[1]];if(m[0].length<block.length)
next.unshift(mk_block(block.substr(m[0].length),block.trailing,block.lineNumber+2));return[header];},code:function code(block,next){var ret=[],re=/^(?: {0,3}\t| {4})(.*)\n?/,lines;if(!block.match(re))return undefined;block_search:do{var b=this.loop_re_over_block(re,block.valueOf(),function(m){ret.push(m[1]);});if(b.length){next.unshift(mk_block(b,block.trailing));break block_search;}
else if(next.length){if(!next[0].match(re))break block_search;ret.push(block.trailing.replace(/[^\n]/g,'').substring(2));block=next.shift();}
else{break block_search;}}while(true);return[["code_block",ret.join("\n")]];},horizRule:function horizRule(block,next){var m=block.match(/^(?:([\s\S]*?)\n)?[ \t]*([-_*])(?:[ \t]*\2){2,}[ \t]*(?:\n([\s\S]*))?$/);if(!m){return undefined;}
var jsonml=[["hr"]];if(m[1]){jsonml.unshift.apply(jsonml,this.processBlock(m[1],[]));}
if(m[3]){next.unshift(mk_block(m[3]));}
return jsonml;},lists:(function(){var any_list="[*+-]|\\d+\\.",bullet_list=/[*+-]/,number_list=/\d+\./,is_list_re=new RegExp("^( {0,3})("+any_list+")[ \t]+"),indent_re="(?: {0,3}\\t| {4})";function regex_for_depth(depth){return new RegExp("(?:^("+indent_re+"{0,"+depth+"} {0,3})("+any_list+")\\s+)|"+"(^"+indent_re+"{0,"+(depth-1)+"}[ ]{0,4})");}
function expand_tab(input){return input.replace(/ {0,3}\t/g,"    ");}
function add(li,loose,inline,nl){if(loose){li.push(["para"].concat(inline));return;}
var add_to=li[li.length-1]instanceof Array&&li[li.length-1][0]=="para"?li[li.length-1]:li;if(nl&&li.length>1)inline.unshift(nl);for(var i=0;i<inline.length;i++){var what=inline[i],is_str=typeof what=="string";if(is_str&&add_to.length>1&&typeof add_to[add_to.length-1]=="string"){add_to[add_to.length-1]+=what;}
else{add_to.push(what);}}}
function get_contained_blocks(depth,blocks){var re=new RegExp("^("+indent_re+"{"+depth+"}.*?\\n?)*$"),replace=new RegExp("^"+indent_re+"{"+depth+"}","gm"),ret=[];while(blocks.length>0){if(re.exec(blocks[0])){var b=blocks.shift(),x=b.replace(replace,"");ret.push(mk_block(x,b.trailing,b.lineNumber));}
break;}
return ret;}
function paragraphify(s,i,stack){var list=s.list;var last_li=list[list.length-1];if(last_li[1]instanceof Array&&last_li[1][0]=="para"){return;}
if(i+1==stack.length){last_li.push(["para"].concat(last_li.splice(1)));}
else{var sublist=last_li.pop();last_li.push(["para"].concat(last_li.splice(1)),sublist);}}
return function(block,next){var m=block.match(is_list_re);if(!m)return undefined;function make_list(m){var list=bullet_list.exec(m[2])?["bulletlist"]:["numberlist"];stack.push({list:list,indent:m[1]});return list;}
var stack=[],list=make_list(m),last_li,loose=false,ret=[stack[0].list],i;loose_search:while(true){var lines=block.split(/(?=\n)/);var li_accumulate="";tight_search:for(var line_no=0;line_no<lines.length;line_no++){var nl="",l=lines[line_no].replace(/^\n/,function(n){nl=n;return"";});var line_re=regex_for_depth(stack.length);m=l.match(line_re);if(m[1]!==undefined){if(li_accumulate.length){add(last_li,loose,this.processInline(li_accumulate),nl);loose=false;li_accumulate="";}
m[1]=expand_tab(m[1]);var wanted_depth=Math.floor(m[1].length/4)+1;if(wanted_depth>stack.length){list=make_list(m);last_li.push(list);last_li=list[1]=["listitem"];}
else{var found=false;for(i=0;i<stack.length;i++){if(stack[i].indent!=m[1])continue;list=stack[i].list;stack.splice(i+1);found=true;break;}
if(!found){wanted_depth++;if(wanted_depth<=stack.length){stack.splice(wanted_depth);list=stack[wanted_depth-1].list;}
else{list=make_list(m);last_li.push(list);}}
last_li=["listitem"];list.push(last_li);}
nl="";}
if(l.length>m[0].length){li_accumulate+=nl+l.substr(m[0].length);}}
if(li_accumulate.length){add(last_li,loose,this.processInline(li_accumulate),nl);loose=false;li_accumulate="";}
var contained=get_contained_blocks(stack.length,next);if(contained.length>0){forEach(stack,paragraphify,this);last_li.push.apply(last_li,this.toTree(contained,[]));}
var next_block=next[0]&&next[0].valueOf()||"";if(next_block.match(is_list_re)||next_block.match(/^ /)){block=next.shift();var hr=this.dialect.block.horizRule(block,next);if(hr){ret.push.apply(ret,hr);break;}
forEach(stack,paragraphify,this);loose=true;continue loose_search;}
break;}
return ret;};})(),blockquote:function blockquote(block,next){if(!block.match(/^>/m))
return undefined;var jsonml=[];if(block[0]!=">"){var lines=block.split(/\n/),prev=[];while(lines.length&&lines[0][0]!=">"){prev.push(lines.shift());}
block=lines.join("\n");jsonml.push.apply(jsonml,this.processBlock(prev.join("\n"),[]));}
while(next.length&&next[0][0]==">"){var b=next.shift();block=new String(block+block.trailing+b);block.trailing=b.trailing;}
var input=block.replace(/^> ?/gm,''),old_tree=this.tree;jsonml.push(this.toTree(input,["blockquote"]));return jsonml;},referenceDefn:function referenceDefn(block,next){var re=/^\s*\[(.*?)\]:\s*(\S+)(?:\s+(?:(['"])(.*?)\3|\((.*?)\)))?\n?/;if(!block.match(re))
return undefined;if(!extract_attr(this.tree)){this.tree.splice(1,0,{});}
var attrs=extract_attr(this.tree);if(attrs.references===undefined){attrs.references={};}
var b=this.loop_re_over_block(re,block,function(m){if(m[2]&&m[2][0]=='<'&&m[2][m[2].length-1]=='>')
m[2]=m[2].substring(1,m[2].length-1);var ref=attrs.references[m[1].toLowerCase()]={href:m[2]};if(m[4]!==undefined)
ref.title=m[4];else if(m[5]!==undefined)
ref.title=m[5];});if(b.length)
next.unshift(mk_block(b,block.trailing));return[];},para:function para(block,next){return[["para"].concat(this.processInline(block))];}}};Markdown.dialects.Gruber.inline={__oneElement__:function oneElement(text,patterns_or_re,previous_nodes){var m,res,lastIndex=0;patterns_or_re=patterns_or_re||this.dialect.inline.__patterns__;var re=new RegExp("([\\s\\S]*?)("+(patterns_or_re.source||patterns_or_re)+")");m=re.exec(text);if(!m){return[text.length,text];}
else if(m[1]){return[m[1].length,m[1]];}
var res;if(m[2]in this.dialect.inline){res=this.dialect.inline[m[2]].call(this,text.substr(m.index),m,previous_nodes||[]);}
res=res||[m[2].length,m[2]];return res;},__call__:function inline(text,patterns){var out=[],res;function add(x){if(typeof x=="string"&&typeof out[out.length-1]=="string")
out[out.length-1]+=x;else
out.push(x);}
while(text.length>0){res=this.dialect.inline.__oneElement__.call(this,text,patterns,out);text=text.substr(res.shift());forEach(res,add)}
return out;},"]":function(){},"}":function(){},"\\":function escaped(text){if(text.match(/^\\[\\`\*_{}\[\]()#\+.!\-]/))
return[2,text[1]];else
return[1,"\\"];},"![":function image(text){var m=text.match(/^!\[(.*?)\][ \t]*\([ \t]*(\S*)(?:[ \t]+(["'])(.*?)\3)?[ \t]*\)/);if(m){if(m[2]&&m[2][0]=='<'&&m[2][m[2].length-1]=='>')
m[2]=m[2].substring(1,m[2].length-1);m[2]=this.dialect.inline.__call__.call(this,m[2],/\\/)[0];var attrs={alt:m[1],href:m[2]||""};if(m[4]!==undefined)
attrs.title=m[4];return[m[0].length,["img",attrs]];}
m=text.match(/^!\[(.*?)\][ \t]*\[(.*?)\]/);if(m){return[m[0].length,["img_ref",{alt:m[1],ref:m[2].toLowerCase(),original:m[0]}]];}
return[2,"!["];},"[":function link(text){var orig=String(text);var res=Markdown.DialectHelpers.inline_until_char.call(this,text.substr(1),']');if(!res)return[1,'['];var consumed=1+res[0],children=res[1],link,attrs;text=text.substr(consumed);var m=text.match(/^\s*\([ \t]*(\S+)(?:[ \t]+(["'])(.*?)\2)?[ \t]*\)/);if(m){var url=m[1];consumed+=m[0].length;if(url&&url[0]=='<'&&url[url.length-1]=='>')
url=url.substring(1,url.length-1);if(!m[3]){var open_parens=1;for(var len=0;len<url.length;len++){switch(url[len]){case'(':open_parens++;break;case')':if(--open_parens==0){consumed-=url.length-len;url=url.substring(0,len);}
break;}}}
url=this.dialect.inline.__call__.call(this,url,/\\/)[0];attrs={href:url||""};if(m[3]!==undefined)
attrs.title=m[3];link=["link",attrs].concat(children);return[consumed,link];}
m=text.match(/^\s*\[(.*?)\]/);if(m){consumed+=m[0].length;attrs={ref:(m[1]||String(children)).toLowerCase(),original:orig.substr(0,consumed)};link=["link_ref",attrs].concat(children);return[consumed,link];}
if(children.length==1&&typeof children[0]=="string"){attrs={ref:children[0].toLowerCase(),original:orig.substr(0,consumed)};link=["link_ref",attrs,children[0]];return[consumed,link];}
return[1,"["];},"<":function autoLink(text){var m;if((m=text.match(/^<(?:((https?|ftp|mailto):[^>]+)|(.*?@.*?\.[a-zA-Z]+))>/))!=null){if(m[3]){return[m[0].length,["link",{href:"mailto:"+m[3]},m[3]]];}
else if(m[2]=="mailto"){return[m[0].length,["link",{href:m[1]},m[1].substr("mailto:".length)]];}
else
return[m[0].length,["link",{href:m[1]},m[1]]];}
return[1,"<"];},"`":function inlineCode(text){var m=text.match(/(`+)(([\s\S]*?)\1)/);if(m&&m[2])
return[m[1].length+m[2].length,["inlinecode",m[3]]];else{return[1,"`"];}},"  \n":function lineBreak(text){return[3,["linebreak"]];}};function strong_em(tag,md){var state_slot=tag+"_state",other_slot=tag=="strong"?"em_state":"strong_state";function CloseTag(len){this.len_after=len;this.name="close_"+md;}
return function(text,orig_match){if(this[state_slot][0]==md){this[state_slot].shift();return[text.length,new CloseTag(text.length-md.length)];}
else{var other=this[other_slot].slice(),state=this[state_slot].slice();this[state_slot].unshift(md);var res=this.processInline(text.substr(md.length));var last=res[res.length-1];var check=this[state_slot].shift();if(last instanceof CloseTag){res.pop();var consumed=text.length-last.len_after;return[consumed,[tag].concat(res)];}
else{this[other_slot]=other;this[state_slot]=state;return[md.length,md];}}};}
Markdown.dialects.Gruber.inline["**"]=strong_em("strong","**");Markdown.dialects.Gruber.inline["__"]=strong_em("strong","__");Markdown.dialects.Gruber.inline["*"]=strong_em("em","*");Markdown.dialects.Gruber.inline["_"]=strong_em("em","_");Markdown.buildBlockOrder=function(d){var ord=[];for(var i in d){if(i=="__order__"||i=="__call__")continue;ord.push(i);}
d.__order__=ord;};Markdown.buildInlinePatterns=function(d){var patterns=[];for(var i in d){if(i.match(/^__.*__$/))continue;var l=i.replace(/([\\.*+?|()\[\]{}])/g,"\\$1").replace(/\n/,"\\n");patterns.push(i.length==1?l:"(?:"+l+")");}
patterns=patterns.join("|");d.__patterns__=patterns;var fn=d.__call__;d.__call__=function(text,pattern){if(pattern!=undefined){return fn.call(this,text,pattern);}
else
{return fn.call(this,text,patterns);}};};Markdown.DialectHelpers={};Markdown.DialectHelpers.inline_until_char=function(text,want){var consumed=0,nodes=[];while(true){if(text[consumed]==want){consumed++;return[consumed,nodes];}
if(consumed>=text.length){return null;}
var res=this.dialect.inline.__oneElement__.call(this,text.substr(consumed));consumed+=res[0];nodes.push.apply(nodes,res.slice(1));}}
Markdown.subclassDialect=function(d){function Block(){}
Block.prototype=d.block;function Inline(){}
Inline.prototype=d.inline;return{block:new Block(),inline:new Inline()};};Markdown.buildBlockOrder(Markdown.dialects.Gruber.block);Markdown.buildInlinePatterns(Markdown.dialects.Gruber.inline);Markdown.dialects.Maruku=Markdown.subclassDialect(Markdown.dialects.Gruber);Markdown.dialects.Maruku.processMetaHash=function processMetaHash(meta_string){var meta=split_meta_hash(meta_string),attr={};for(var i=0;i<meta.length;++i){if(/^#/.test(meta[i])){attr.id=meta[i].substring(1);}
else if(/^\./.test(meta[i])){if(attr['class']){attr['class']=attr['class']+meta[i].replace(/./," ");}
else{attr['class']=meta[i].substring(1);}}
else if(/\=/.test(meta[i])){var s=meta[i].split(/\=/);attr[s[0]]=s[1];}}
return attr;}
function split_meta_hash(meta_string){var meta=meta_string.split(""),parts=[""],in_quotes=false;while(meta.length){var letter=meta.shift();switch(letter){case" ":if(in_quotes){parts[parts.length-1]+=letter;}
else{parts.push("");}
break;case"'":case'"':in_quotes=!in_quotes;break;case"\\":letter=meta.shift();default:parts[parts.length-1]+=letter;break;}}
return parts;}
Markdown.dialects.Maruku.block.document_meta=function document_meta(block,next){if(block.lineNumber>1)return undefined;if(!block.match(/^(?:\w+:.*\n)*\w+:.*$/))return undefined;if(!extract_attr(this.tree)){this.tree.splice(1,0,{});}
var pairs=block.split(/\n/);for(p in pairs){var m=pairs[p].match(/(\w+):\s*(.*)$/),key=m[1].toLowerCase(),value=m[2];this.tree[1][key]=value;}
return[];};Markdown.dialects.Maruku.block.block_meta=function block_meta(block,next){var m=block.match(/(^|\n) {0,3}\{:\s*((?:\\\}|[^\}])*)\s*\}$/);if(!m)return undefined;var attr=this.dialect.processMetaHash(m[2]);var hash;if(m[1]===""){var node=this.tree[this.tree.length-1];hash=extract_attr(node);if(typeof node==="string")return undefined;if(!hash){hash={};node.splice(1,0,hash);}
for(a in attr){hash[a]=attr[a];}
return[];}
var b=block.replace(/\n.*$/,""),result=this.processBlock(b,[]);hash=extract_attr(result[0]);if(!hash){hash={};result[0].splice(1,0,hash);}
for(a in attr){hash[a]=attr[a];}
return result;};Markdown.dialects.Maruku.block.definition_list=function definition_list(block,next){var tight=/^((?:[^\s:].*\n)+):\s+([\s\S]+)$/,list=["dl"],i;if((m=block.match(tight))){var blocks=[block];while(next.length&&tight.exec(next[0])){blocks.push(next.shift());}
for(var b=0;b<blocks.length;++b){var m=blocks[b].match(tight),terms=m[1].replace(/\n$/,"").split(/\n/),defns=m[2].split(/\n:\s+/);for(i=0;i<terms.length;++i){list.push(["dt",terms[i]]);}
for(i=0;i<defns.length;++i){list.push(["dd"].concat(this.processInline(defns[i].replace(/(\n)\s+/,"$1"))));}}}
else{return undefined;}
return[list];};Markdown.dialects.Maruku.inline["{:"]=function inline_meta(text,matches,out){if(!out.length){return[2,"{:"];}
var before=out[out.length-1];if(typeof before==="string"){return[2,"{:"];}
var m=text.match(/^\{:\s*((?:\\\}|[^\}])*)\s*\}/);if(!m){return[2,"{:"];}
var meta=this.dialect.processMetaHash(m[1]),attr=extract_attr(before);if(!attr){attr={};before.splice(1,0,attr);}
for(var k in meta){attr[k]=meta[k];}
return[m[0].length,""];};Markdown.buildBlockOrder(Markdown.dialects.Maruku.block);Markdown.buildInlinePatterns(Markdown.dialects.Maruku.inline);var isArray=Array.isArray||function(obj){return Object.prototype.toString.call(obj)=='[object Array]';};var forEach;if(Array.prototype.forEach){forEach=function(arr,cb,thisp){return arr.forEach(cb,thisp);};}
else{forEach=function(arr,cb,thisp){for(var i=0;i<arr.length;i++){cb.call(thisp||arr,arr[i],i,arr);}}}
function extract_attr(jsonml){return isArray(jsonml)&&jsonml.length>1&&typeof jsonml[1]==="object"&&!(isArray(jsonml[1]))?jsonml[1]:undefined;}
expose.renderJsonML=function(jsonml,options){options=options||{};options.root=options.root||false;var content=[];if(options.root){content.push(render_tree(jsonml));}
else{jsonml.shift();if(jsonml.length&&typeof jsonml[0]==="object"&&!(jsonml[0]instanceof Array)){jsonml.shift();}
while(jsonml.length){content.push(render_tree(jsonml.shift()));}}
return content.join("\n\n");};function escapeHTML(text){return text.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;");}
function render_tree(jsonml){if(typeof jsonml==="string"){return escapeHTML(jsonml);}
var tag=jsonml.shift(),attributes={},content=[];if(jsonml.length&&typeof jsonml[0]==="object"&&!(jsonml[0]instanceof Array)){attributes=jsonml.shift();}
while(jsonml.length){content.push(arguments.callee(jsonml.shift()));}
var tag_attrs="";for(var a in attributes){tag_attrs+=" "+a+'="'+escapeHTML(attributes[a])+'"';}
if(tag=="img"||tag=="br"||tag=="hr"){return"<"+tag+tag_attrs+"/>";}
else{return"<"+tag+tag_attrs+">"+content.join("")+"</"+tag+">";}}
function convert_tree_to_html(tree,references,options){var i;options=options||{};var jsonml=tree.slice(0);if(typeof options.preprocessTreeNode==="function"){jsonml=options.preprocessTreeNode(jsonml,references);}
var attrs=extract_attr(jsonml);if(attrs){jsonml[1]={};for(i in attrs){jsonml[1][i]=attrs[i];}
attrs=jsonml[1];}
if(typeof jsonml==="string"){return jsonml;}
switch(jsonml[0]){case"header":jsonml[0]="h"+jsonml[1].level;delete jsonml[1].level;break;case"bulletlist":jsonml[0]="ul";break;case"numberlist":jsonml[0]="ol";break;case"listitem":jsonml[0]="li";break;case"para":jsonml[0]="p";break;case"markdown":jsonml[0]="html";if(attrs)delete attrs.references;break;case"code_block":jsonml[0]="pre";i=attrs?2:1;var code=["code"];code.push.apply(code,jsonml.splice(i));jsonml[i]=code;break;case"inlinecode":jsonml[0]="code";break;case"img":jsonml[1].src=jsonml[1].href;delete jsonml[1].href;break;case"linebreak":jsonml[0]="br";break;case"link":jsonml[0]="a";break;case"link_ref":jsonml[0]="a";var ref=references[attrs.ref];if(ref){delete attrs.ref;attrs.href=ref.href;if(ref.title){attrs.title=ref.title;}
delete attrs.original;}
else{return attrs.original;}
break;case"img_ref":jsonml[0]="img";var ref=references[attrs.ref];if(ref){delete attrs.ref;attrs.src=ref.href;if(ref.title){attrs.title=ref.title;}
delete attrs.original;}
else{return attrs.original;}
break;}
i=1;if(attrs){for(var key in jsonml[1]){i=2;}
if(i===1){jsonml.splice(i,1);}}
for(;i<jsonml.length;++i){jsonml[i]=arguments.callee(jsonml[i],references,options);}
return jsonml;}
function merge_text_nodes(jsonml){var i=extract_attr(jsonml)?2:1;while(i<jsonml.length){if(typeof jsonml[i]==="string"){if(i+1<jsonml.length&&typeof jsonml[i+1]==="string"){jsonml[i]+=jsonml.splice(i+1,1)[0];}
else{++i;}}
else{arguments.callee(jsonml[i]);++i;}}}})((function(){if(typeof exports==="undefined"){window.markdown={};return window.markdown;}
else{return exports;}})());
(function(f){if(typeof exports==="object"&&typeof module!=="undefined"){module.exports=f()}else if(typeof define==="function"&&define.amd){define([],f)}else{var g;if(typeof window!=="undefined"){g=window}else if(typeof global!=="undefined"){g=global}else if(typeof self!=="undefined"){g=self}else{g=this}g.toMarkdown=f()}})(function(){var define,module,exports;return(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){'use strict'
var toMarkdown
var converters
var mdConverters=require('./lib/md-converters')
var gfmConverters=require('./lib/gfm-converters')
var HtmlParser=require('./lib/html-parser')
var collapse=require('collapse-whitespace')
var blocks=['address','article','aside','audio','blockquote','body','canvas','center','dd','dir','div','dl','dt','fieldset','figcaption','figure','footer','form','frameset','h1','h2','h3','h4','h5','h6','header','hgroup','hr','html','isindex','li','main','menu','nav','noframes','noscript','ol','output','p','pre','section','table','tbody','td','tfoot','th','thead','tr','ul']
function isBlock(node){return blocks.indexOf(node.nodeName.toLowerCase())!==-1}
var voids=['area','base','br','col','command','embed','hr','img','input','keygen','link','meta','param','source','track','wbr']
function isVoid(node){return voids.indexOf(node.nodeName.toLowerCase())!==-1}
function htmlToDom(string){var tree=new HtmlParser().parseFromString(string,'text/html')
collapse(tree.documentElement,isBlock)
return tree}
function bfsOrder(node){var inqueue=[node]
var outqueue=[]
var elem
var children
var i
while(inqueue.length>0){elem=inqueue.shift()
outqueue.push(elem)
children=elem.childNodes
for(i=0;i<children.length;i++){if(children[i].nodeType===1)inqueue.push(children[i])}}
outqueue.shift()
return outqueue}
function getContent(node){var text=''
for(var i=0;i<node.childNodes.length;i++){if(node.childNodes[i].nodeType===1){text+=node.childNodes[i]._replacement}else if(node.childNodes[i].nodeType===3){text+=node.childNodes[i].data}else continue}
return text}
function outer(node,content){return node.cloneNode(false).outerHTML.replace('><','>'+content+'<')}
function canConvert(node,filter){if(typeof filter==='string'){return filter===node.nodeName.toLowerCase()}
if(Array.isArray(filter)){return filter.indexOf(node.nodeName.toLowerCase())!==-1}else if(typeof filter==='function'){return filter.call(toMarkdown,node)}else{throw new TypeError('`filter` needs to be a string, array, or function')}}
function isFlankedByWhitespace(side,node){var sibling
var regExp
var isFlanked
if(side==='left'){sibling=node.previousSibling
regExp=/ $/}else{sibling=node.nextSibling
regExp=/^ /}
if(sibling){if(sibling.nodeType===3){isFlanked=regExp.test(sibling.nodeValue)}else if(sibling.nodeType===1&&!isBlock(sibling)){isFlanked=regExp.test(sibling.textContent)}}
return isFlanked}
function flankingWhitespace(node){var leading=''
var trailing=''
if(!isBlock(node)){var hasLeading=/^[ \r\n\t]/.test(node.innerHTML)
var hasTrailing=/[ \r\n\t]$/.test(node.innerHTML)
if(hasLeading&&!isFlankedByWhitespace('left',node)){leading=' '}
if(hasTrailing&&!isFlankedByWhitespace('right',node)){trailing=' '}}
return{leading:leading,trailing:trailing}}
function process(node){var replacement
var content=getContent(node)
if(!isVoid(node)&&!/A|TH|TD/.test(node.nodeName)&&/^\s*$/i.test(content)){node._replacement=''
return}
for(var i=0;i<converters.length;i++){var converter=converters[i]
if(canConvert(node,converter.filter)){if(typeof converter.replacement!=='function'){throw new TypeError('`replacement` needs to be a function that returns a string')}
var whitespace=flankingWhitespace(node)
if(whitespace.leading||whitespace.trailing){content=content.trim()}
replacement=whitespace.leading+
converter.replacement.call(toMarkdown,content,node)+
whitespace.trailing
break}}
node._replacement=replacement}
toMarkdown=function(input,options){options=options||{}
if(typeof input!=='string'){throw new TypeError(input+' is not a string')}
input=input.replace(/(\d+)\. /g,'$1\\. ')
var clone=htmlToDom(input).body
var nodes=bfsOrder(clone)
var output
converters=mdConverters.slice(0)
if(options.gfm){converters=gfmConverters.concat(converters)}
if(options.converters){converters=options.converters.concat(converters)}
for(var i=nodes.length-1;i>=0;i--){process(nodes[i])}
output=getContent(clone)
return output.replace(/^[\t\r\n]+|[\t\r\n\s]+$/g,'').replace(/\n\s+\n/g,'\n\n').replace(/\n{3,}/g,'\n\n')}
toMarkdown.isBlock=isBlock
toMarkdown.isVoid=isVoid
toMarkdown.outer=outer
module.exports=toMarkdown},{"./lib/gfm-converters":2,"./lib/html-parser":3,"./lib/md-converters":4,"collapse-whitespace":7}],2:[function(require,module,exports){'use strict'
function cell(content,node){var index=Array.prototype.indexOf.call(node.parentNode.childNodes,node)
var prefix=' '
if(index===0)prefix='| '
return prefix+content+' |'}
var highlightRegEx=/highlight highlight-(\S+)/
module.exports=[{filter:'br',replacement:function(){return'\n'}},{filter:['del','s','strike'],replacement:function(content){return'~~'+content+'~~'}},{filter:function(node){return node.type==='checkbox'&&node.parentNode.nodeName==='LI'},replacement:function(content,node){return(node.checked?'[x]':'[ ]')+' '}},{filter:['th','td'],replacement:function(content,node){return cell(content,node)}},{filter:'tr',replacement:function(content,node){var borderCells=''
var alignMap={left:':--',right:'--:',center:':-:'}
if(node.parentNode.nodeName==='THEAD'){for(var i=0;i<node.childNodes.length;i++){var align=node.childNodes[i].attributes.align
var border='---'
if(align)border=alignMap[align.value]||border
borderCells+=cell(border,node.childNodes[i])}}
return'\n'+content+(borderCells?'\n'+borderCells:'')}},{filter:'table',replacement:function(content){return'\n\n'+content+'\n\n'}},{filter:['thead','tbody','tfoot'],replacement:function(content){return content}},{filter:function(node){return node.nodeName==='PRE'&&node.firstChild&&node.firstChild.nodeName==='CODE'},replacement:function(content,node){return'\n\n```\n'+node.firstChild.textContent+'\n```\n\n'}},{filter:function(node){return node.nodeName==='PRE'&&node.parentNode.nodeName==='DIV'&&highlightRegEx.test(node.parentNode.className)},replacement:function(content,node){var language=node.parentNode.className.match(highlightRegEx)[1]
return'\n\n```'+language+'\n'+node.textContent+'\n```\n\n'}},{filter:function(node){return node.nodeName==='DIV'&&highlightRegEx.test(node.className)},replacement:function(content){return'\n\n'+content+'\n\n'}}]},{}],3:[function(require,module,exports){var _window=(typeof window!=='undefined'?window:this)
function canParseHtmlNatively(){var Parser=_window.DOMParser
var canParse=false
try{if(new Parser().parseFromString('','text/html')){canParse=true}}catch(e){}
return canParse}
function createHtmlParser(){var Parser=function(){}
if(typeof document==='undefined'){var jsdom=require('jsdom')
Parser.prototype.parseFromString=function(string){return jsdom.jsdom(string,{features:{FetchExternalResources:[],ProcessExternalResources:false}})}}else{if(!shouldUseActiveX()){Parser.prototype.parseFromString=function(string){var doc=document.implementation.createHTMLDocument('')
doc.open()
doc.write(string)
doc.close()
return doc}}else{Parser.prototype.parseFromString=function(string){var doc=new window.ActiveXObject('htmlfile')
doc.designMode='on'
doc.open()
doc.write(string)
doc.close()
return doc}}}
return Parser}
function shouldUseActiveX(){var useActiveX=false
try{document.implementation.createHTMLDocument('').open()}catch(e){if(window.ActiveXObject)useActiveX=true}
return useActiveX}
module.exports=canParseHtmlNatively()?_window.DOMParser:createHtmlParser()},{"jsdom":6}],4:[function(require,module,exports){'use strict'
module.exports=[{filter:'p',replacement:function(content){return'\n\n'+content+'\n\n'}},{filter:'br',replacement:function(){return'  \n'}},{filter:['h1','h2','h3','h4','h5','h6'],replacement:function(content,node){var hLevel=node.nodeName.charAt(1)
var hPrefix=''
for(var i=0;i<hLevel;i++){hPrefix+='#'}
return'\n\n'+hPrefix+' '+content+'\n\n'}},{filter:'hr',replacement:function(){return'\n\n* * *\n\n'}},{filter:['em','i'],replacement:function(content){return'_'+content+'_'}},{filter:['strong','b'],replacement:function(content){return'**'+content+'**'}},{filter:function(node){var hasSiblings=node.previousSibling||node.nextSibling
var isCodeBlock=node.parentNode.nodeName==='PRE'&&!hasSiblings
return node.nodeName==='CODE'&&!isCodeBlock},replacement:function(content){return'`'+content+'`'}},{filter:function(node){return node.nodeName==='A'&&node.getAttribute('href')},replacement:function(content,node){var titlePart=node.title?' "'+node.title+'"':''
return'['+content+']('+node.getAttribute('href')+titlePart+')'}},{filter:'img',replacement:function(content,node){var alt=node.alt||''
var src=node.getAttribute('src')||''
var title=node.title||''
var titlePart=title?' "'+title+'"':''
return src?'!['+alt+']'+'('+src+titlePart+')':''}},{filter:function(node){return node.nodeName==='PRE'&&node.firstChild.nodeName==='CODE'},replacement:function(content,node){return'\n\n    '+node.firstChild.textContent.replace(/\n/g,'\n    ')+'\n\n'}},{filter:'blockquote',replacement:function(content){content=content.trim()
content=content.replace(/\n{3,}/g,'\n\n')
content=content.replace(/^/gm,'> ')
return'\n\n'+content+'\n\n'}},{filter:'li',replacement:function(content,node){content=content.replace(/^\s+/,'').replace(/\n/gm,'\n    ')
var prefix='*   '
var parent=node.parentNode
var index=Array.prototype.indexOf.call(parent.children,node)+1
prefix=/ol/i.test(parent.nodeName)?index+'.  ':'*   '
return prefix+content}},{filter:['ul','ol'],replacement:function(content,node){var strings=[]
for(var i=0;i<node.childNodes.length;i++){strings.push(node.childNodes[i]._replacement)}
if(/li/i.test(node.parentNode.nodeName)){return'\n'+strings.join('\n')}
return'\n\n'+strings.join('\n')+'\n\n'}},{filter:function(node){return this.isBlock(node)},replacement:function(content,node){return'\n\n'+this.outer(node,content)+'\n\n'}},{filter:function(){return true},replacement:function(content,node){return this.outer(node,content)}}]},{}],5:[function(require,module,exports){module.exports=["address","article","aside","audio","blockquote","canvas","dd","div","dl","fieldset","figcaption","figure","footer","form","h1","h2","h3","h4","h5","h6","header","hgroup","hr","main","nav","noscript","ol","output","p","pre","section","table","tfoot","ul","video"];},{}],6:[function(require,module,exports){},{}],7:[function(require,module,exports){'use strict';var voidElements=require('void-elements');Object.keys(voidElements).forEach(function(name){voidElements[name.toUpperCase()]=1;});var blockElements={};require('block-elements').forEach(function(name){blockElements[name.toUpperCase()]=1;});function isBlockElem(node){return!!(node&&blockElements[node.nodeName]);}
function isVoid(node){return!!(node&&voidElements[node.nodeName]);}
function collapseWhitespace(elem,isBlock){if(!elem.firstChild||elem.nodeName==='PRE')return;if(typeof isBlock!=='function'){isBlock=isBlockElem;}
var prevText=null;var prevVoid=false;var prev=null;var node=next(prev,elem);while(node!==elem){if(node.nodeType===3){var text=node.data.replace(/[ \r\n\t]+/g,' ');if((!prevText||/ $/.test(prevText.data))&&!prevVoid&&text[0]===' '){text=text.substr(1);}
if(!text){node=remove(node);continue;}
node.data=text;prevText=node;}else if(node.nodeType===1){if(isBlock(node)||node.nodeName==='BR'){if(prevText){prevText.data=prevText.data.replace(/ $/,'');}
prevText=null;prevVoid=false;}else if(isVoid(node)){prevText=null;prevVoid=true;}}else{node=remove(node);continue;}
var nextNode=next(prev,node);prev=node;node=nextNode;}
if(prevText){prevText.data=prevText.data.replace(/ $/,'');if(!prevText.data){remove(prevText);}}}
function remove(node){var next=node.nextSibling||node.parentNode;node.parentNode.removeChild(node);return next;}
function next(prev,current){if(prev&&prev.parentNode===current||current.nodeName==='PRE'){return current.nextSibling||current.parentNode;}
return current.firstChild||current.nextSibling||current.parentNode;}
module.exports=collapseWhitespace;},{"block-elements":5,"void-elements":8}],8:[function(require,module,exports){module.exports={"area":true,"base":true,"br":true,"col":true,"embed":true,"hr":true,"img":true,"input":true,"keygen":true,"link":true,"menuitem":true,"meta":true,"param":true,"source":true,"track":true,"wbr":true};},{}]},{},[1])(1)});
(function(factory){if(typeof define==="function"&&define.amd){define(["jquery"],factory);}else if(typeof exports==='object'){factory(require('jquery'));}else{factory(jQuery);}}(function($){"use strict";var Markdown=function(element,options){var opts=['autofocus','savable','hideable','width','height','resize','iconlibrary','language','footer','fullscreen','hiddenButtons','disabledButtons'];$.each(opts,function(_,opt){if(typeof $(element).data(opt)!=='undefined'){options=typeof options=='object'?options:{}
options[opt]=$(element).data(opt)}});this.$ns='bootstrap-markdown';this.$element=$(element);this.$editable={el:null,type:null,attrKeys:[],attrValues:[],content:null};this.$options=$.extend(true,{},$.fn.markdown.defaults,options,this.$element.data('options'));this.$oldContent=null;this.$isPreview=false;this.$isFullscreen=false;this.$editor=null;this.$textarea=null;this.$handler=[];this.$callback=[];this.$nextTab=[];this.showEditor();};Markdown.prototype={constructor:Markdown,__alterButtons:function(name,alter){var handler=this.$handler,isAll=(name=='all'),that=this;$.each(handler,function(k,v){var halt=true;if(isAll){halt=false;}else{halt=v.indexOf(name)<0;}
if(halt===false){alter(that.$editor.find('button[data-handler="'+v+'"]'));}});},__buildButtons:function(buttonsArray,container){var i,ns=this.$ns,handler=this.$handler,callback=this.$callback;for(i=0;i<buttonsArray.length;i++){var y,btnGroups=buttonsArray[i];for(y=0;y<btnGroups.length;y++){var z,buttons=btnGroups[y].data,btnGroupContainer=$('<div/>',{'class':'btn-group'});for(z=0;z<buttons.length;z++){var button=buttons[z],buttonContainer,buttonIconContainer,buttonHandler=ns+'-'+button.name,buttonIcon=this.__getIcon(button.icon),btnText=button.btnText?button.btnText:'',btnClass=button.btnClass?button.btnClass:'btn',tabIndex=button.tabIndex?button.tabIndex:'-1',hotkey=typeof button.hotkey!=='undefined'?button.hotkey:'',hotkeyCaption=typeof jQuery.hotkeys!=='undefined'&&hotkey!==''?' ('+hotkey+')':'';buttonContainer=$('<button></button>');buttonContainer.text(' '+this.__localize(btnText)).addClass('btn-default btn-sm').addClass(btnClass);if(btnClass.match(/btn\-(primary|success|info|warning|danger|link)/)){buttonContainer.removeClass('btn-default');}
buttonContainer.attr({'type':'button','title':this.__localize(button.title)+hotkeyCaption,'tabindex':tabIndex,'data-provider':ns,'data-handler':buttonHandler,'data-hotkey':hotkey});if(button.toggle===true){buttonContainer.attr('data-toggle','button');}
buttonIconContainer=$('<span/>');buttonIconContainer.addClass(buttonIcon);buttonIconContainer.prependTo(buttonContainer);btnGroupContainer.append(buttonContainer);handler.push(buttonHandler);callback.push(button.callback);}
container.append(btnGroupContainer);}}
return container;},__setListener:function(){var hasRows=typeof this.$textarea.attr('rows')!=='undefined',maxRows=this.$textarea.val().split("\n").length>5?this.$textarea.val().split("\n").length:'5',rowsVal=hasRows?this.$textarea.attr('rows'):maxRows;this.$textarea.attr('rows',rowsVal);if(this.$options.resize){this.$textarea.css('resize',this.$options.resize);}
this.$textarea.on({'focus':$.proxy(this.focus,this),'keyup':$.proxy(this.keyup,this),'change':$.proxy(this.change,this),'select':$.proxy(this.select,this)});if(this.eventSupported('keydown')){this.$textarea.on('keydown',$.proxy(this.keydown,this));}
if(this.eventSupported('keypress')){this.$textarea.on('keypress',$.proxy(this.keypress,this))}
this.$textarea.data('markdown',this);},__handle:function(e){var target=$(e.currentTarget),handler=this.$handler,callback=this.$callback,handlerName=target.attr('data-handler'),callbackIndex=handler.indexOf(handlerName),callbackHandler=callback[callbackIndex];$(e.currentTarget).focus();callbackHandler(this);this.change(this);if(handlerName.indexOf('cmdSave')<0){this.$textarea.focus();}
e.preventDefault();},__localize:function(string){var messages=$.fn.markdown.messages,language=this.$options.language;if(typeof messages!=='undefined'&&typeof messages[language]!=='undefined'&&typeof messages[language][string]!=='undefined'){return messages[language][string];}
return string;},__getIcon:function(src){return typeof src=='object'?src[this.$options.iconlibrary]:src;},setFullscreen:function(mode){var $editor=this.$editor,$textarea=this.$textarea;if(mode===true){$editor.addClass('md-fullscreen-mode');$('body').addClass('md-nooverflow');this.$options.onFullscreen(this);}else{$editor.removeClass('md-fullscreen-mode');$('body').removeClass('md-nooverflow');this.$options.onFullscreenExit(this);if(this.$isPreview==true)this.hidePreview().showPreview()}
this.$isFullscreen=mode;$textarea.focus();},showEditor:function(){var instance=this,textarea,ns=this.$ns,container=this.$element,originalHeigth=container.css('height'),originalWidth=container.css('width'),editable=this.$editable,handler=this.$handler,callback=this.$callback,options=this.$options,editor=$('<div/>',{'class':'md-editor',click:function(){instance.focus();}});if(this.$editor===null){var editorHeader=$('<div/>',{'class':'md-header btn-toolbar'});var allBtnGroups=[];if(options.buttons.length>0)allBtnGroups=allBtnGroups.concat(options.buttons[0]);if(options.additionalButtons.length>0){$.each(options.additionalButtons[0],function(idx,buttonGroup){var matchingGroups=$.grep(allBtnGroups,function(allButtonGroup,allIdx){return allButtonGroup.name===buttonGroup.name;});if(matchingGroups.length>0){matchingGroups[0].data=matchingGroups[0].data.concat(buttonGroup.data);}else{allBtnGroups.push(options.additionalButtons[0][idx]);}});}
if(options.reorderButtonGroups.length>0){allBtnGroups=allBtnGroups.filter(function(btnGroup){return options.reorderButtonGroups.indexOf(btnGroup.name)>-1;}).sort(function(a,b){if(options.reorderButtonGroups.indexOf(a.name)<options.reorderButtonGroups.indexOf(b.name))return-1;if(options.reorderButtonGroups.indexOf(a.name)>options.reorderButtonGroups.indexOf(b.name))return 1;return 0;});}
if(allBtnGroups.length>0){editorHeader=this.__buildButtons([allBtnGroups],editorHeader);}
if(options.fullscreen.enable){editorHeader.append('<div class="md-controls"><a class="md-control md-control-fullscreen" href="#"><span class="'+this.__getIcon(options.fullscreen.icons.fullscreenOn)+'"></span></a></div>').on('click','.md-control-fullscreen',function(e){e.preventDefault();instance.setFullscreen(true);});}
editor.append(editorHeader);if(container.is('textarea')){container.before(editor);textarea=container;textarea.addClass('md-input');editor.append(textarea);}else{var rawContent=(typeof toMarkdown=='function')?toMarkdown(container.html()):container.html(),currentContent=$.trim(rawContent);textarea=$('<textarea/>',{'class':'md-input','val':currentContent});editor.append(textarea);editable.el=container;editable.type=container.prop('tagName').toLowerCase();editable.content=container.html();$(container[0].attributes).each(function(){editable.attrKeys.push(this.nodeName);editable.attrValues.push(this.nodeValue);});container.replaceWith(editor);}
var editorFooter=$('<div/>',{'class':'md-footer'}),createFooter=false,footer='';if(options.savable){createFooter=true;var saveHandler='cmdSave';handler.push(saveHandler);callback.push(options.onSave);editorFooter.append('<button class="btn btn-success" data-provider="'
+ns
+'" data-handler="'
+saveHandler
+'"><i class="icon icon-white icon-ok"></i> '
+this.__localize('Save')
+'</button>');}
footer=typeof options.footer==='function'?options.footer(this):options.footer;if($.trim(footer)!==''){createFooter=true;editorFooter.append(footer);}
if(createFooter)editor.append(editorFooter);if(options.width&&options.width!=='inherit'){if(jQuery.isNumeric(options.width)){editor.css('display','table');textarea.css('width',options.width+'px');}else{editor.addClass(options.width);}}
if(options.height&&options.height!=='inherit'){if(jQuery.isNumeric(options.height)){var height=options.height;if(editorHeader)height=Math.max(0,height-editorHeader.outerHeight());if(editorFooter)height=Math.max(0,height-editorFooter.outerHeight());textarea.css('height',height+'px');}else{editor.addClass(options.height);}}
this.$editor=editor;this.$textarea=textarea;this.$editable=editable;this.$oldContent=this.getContent();this.__setListener();this.$editor.attr('id',(new Date()).getTime());this.$editor.on('click','[data-provider="bootstrap-markdown"]',$.proxy(this.__handle,this));if(this.$element.is(':disabled')||this.$element.is('[readonly]')){this.$editor.addClass('md-editor-disabled');this.disableButtons('all');}
if(this.eventSupported('keydown')&&typeof jQuery.hotkeys==='object'){editorHeader.find('[data-provider="bootstrap-markdown"]').each(function(){var $button=$(this),hotkey=$button.attr('data-hotkey');if(hotkey.toLowerCase()!==''){textarea.bind('keydown',hotkey,function(){$button.trigger('click');return false;});}});}
if(options.initialstate==='preview'){this.showPreview();}else if(options.initialstate==='fullscreen'&&options.fullscreen.enable){this.setFullscreen(true);}}else{this.$editor.show();}
if(options.autofocus){this.$textarea.focus();this.$editor.addClass('active');}
if(options.fullscreen.enable&&options.fullscreen!==false){this.$editor.append('<div class="md-fullscreen-controls">'
+'<a href="#" class="exit-fullscreen" title="Exit fullscreen"><span class="'+this.__getIcon(options.fullscreen.icons.fullscreenOff)+'">'
+'</span></a>'
+'</div>');this.$editor.on('click','.exit-fullscreen',function(e){e.preventDefault();instance.setFullscreen(false);});}
this.hideButtons(options.hiddenButtons);this.disableButtons(options.disabledButtons);if(options.dropZoneOptions){if(this.$editor.dropzone){options.dropZoneOptions.init=function(){var caretPos=0;this.on('drop',function(e){caretPos=textarea.prop('selectionStart');});this.on('success',function(file,path){var text=textarea.val();textarea.val(text.substring(0,caretPos)+'\n![description]('+path+')\n'+text.substring(caretPos));});this.on('error',function(file,error,xhr){console.log('Error:',error);});}
this.$textarea.addClass('dropzone');this.$editor.dropzone(options.dropZoneOptions);}else{console.log('dropZoneOptions was configured, but DropZone was not detected.');}}
options.onShow(this);return this;},parseContent:function(val){var content;var val=val||this.$textarea.val();if(this.$options.parser){content=this.$options.parser(val);}else if(typeof markdown=='object'){content=markdown.toHTML(val);}else if(typeof marked=='function'){content=marked(val);}else{content=val;}
return content;},showPreview:function(){var options=this.$options,container=this.$textarea,afterContainer=container.next(),replacementContainer=$('<div/>',{'class':'md-preview','data-provider':'markdown-preview'}),content,callbackContent;if(this.$isPreview==true){return this;}
this.$isPreview=true;this.disableButtons('all').enableButtons('cmdPreview');callbackContent=options.onPreview(this);content=typeof callbackContent=='string'?callbackContent:this.parseContent();replacementContainer.html(content);if(afterContainer&&afterContainer.attr('class')=='md-footer'){replacementContainer.insertBefore(afterContainer);}else{container.parent().append(replacementContainer);}
replacementContainer.css({width:container.outerWidth()+'px',height:container.outerHeight()+'px'});if(this.$options.resize){replacementContainer.css('resize',this.$options.resize);}
container.hide();replacementContainer.data('markdown',this);if(this.$element.is(':disabled')||this.$element.is('[readonly]')){this.$editor.addClass('md-editor-disabled');this.disableButtons('all');}
return this;},hidePreview:function(){this.$isPreview=false;var container=this.$editor.find('div[data-provider="markdown-preview"]');container.remove();this.enableButtons('all');this.disableButtons(this.$options.disabledButtons);this.$textarea.show();this.__setListener();return this;},isDirty:function(){return this.$oldContent!=this.getContent();},getContent:function(){return this.$textarea.val();},setContent:function(content){this.$textarea.val(content);return this;},findSelection:function(chunk){var content=this.getContent(),startChunkPosition;if(startChunkPosition=content.indexOf(chunk),startChunkPosition>=0&&chunk.length>0){var oldSelection=this.getSelection(),selection;this.setSelection(startChunkPosition,startChunkPosition+chunk.length);selection=this.getSelection();this.setSelection(oldSelection.start,oldSelection.end);return selection;}else{return null;}},getSelection:function(){var e=this.$textarea[0];return(('selectionStart'in e&&function(){var l=e.selectionEnd-e.selectionStart;return{start:e.selectionStart,end:e.selectionEnd,length:l,text:e.value.substr(e.selectionStart,l)};})||function(){return null;})();},setSelection:function(start,end){var e=this.$textarea[0];return(('selectionStart'in e&&function(){e.selectionStart=start;e.selectionEnd=end;return;})||function(){return null;})();},replaceSelection:function(text){var e=this.$textarea[0];return(('selectionStart'in e&&function(){e.value=e.value.substr(0,e.selectionStart)+text+e.value.substr(e.selectionEnd,e.value.length);e.selectionStart=e.value.length;return this;})||function(){e.value+=text;return jQuery(e);})();},getNextTab:function(){if(this.$nextTab.length===0){return null;}else{var nextTab,tab=this.$nextTab.shift();if(typeof tab=='function'){nextTab=tab();}else if(typeof tab=='object'&&tab.length>0){nextTab=tab;}
return nextTab;}},setNextTab:function(start,end){if(typeof start=='string'){var that=this;this.$nextTab.push(function(){return that.findSelection(start);});}else if(typeof start=='number'&&typeof end=='number'){var oldSelection=this.getSelection();this.setSelection(start,end);this.$nextTab.push(this.getSelection());this.setSelection(oldSelection.start,oldSelection.end);}
return;},__parseButtonNameParam:function(names){return typeof names=='string'?names.split(' '):names;},enableButtons:function(name){var buttons=this.__parseButtonNameParam(name),that=this;$.each(buttons,function(i,v){that.__alterButtons(buttons[i],function(el){el.removeAttr('disabled');});});return this;},disableButtons:function(name){var buttons=this.__parseButtonNameParam(name),that=this;$.each(buttons,function(i,v){that.__alterButtons(buttons[i],function(el){el.attr('disabled','disabled');});});return this;},hideButtons:function(name){var buttons=this.__parseButtonNameParam(name),that=this;$.each(buttons,function(i,v){that.__alterButtons(buttons[i],function(el){el.addClass('hidden');});});return this;},showButtons:function(name){var buttons=this.__parseButtonNameParam(name),that=this;$.each(buttons,function(i,v){that.__alterButtons(buttons[i],function(el){el.removeClass('hidden');});});return this;},eventSupported:function(eventName){var isSupported=eventName in this.$element;if(!isSupported){this.$element.setAttribute(eventName,'return;');isSupported=typeof this.$element[eventName]==='function';}
return isSupported;},keyup:function(e){var blocked=false;switch(e.keyCode){case 40:case 38:case 16:case 17:case 18:break;case 9:var nextTab;if(nextTab=this.getNextTab(),nextTab!==null){var that=this;setTimeout(function(){that.setSelection(nextTab.start,nextTab.end);},500);blocked=true;}else{var cursor=this.getSelection();if(cursor.start==cursor.end&&cursor.end==this.getContent().length){blocked=false;}else{this.setSelection(this.getContent().length,this.getContent().length);blocked=true;}}
break;case 13:blocked=false;break;case 27:if(this.$isFullscreen)this.setFullscreen(false);blocked=false;break;default:blocked=false;}
if(blocked){e.stopPropagation();e.preventDefault();}
this.$options.onChange(this);},change:function(e){this.$options.onChange(this);return this;},select:function(e){this.$options.onSelect(this);return this;},focus:function(e){var options=this.$options,isHideable=options.hideable,editor=this.$editor;editor.addClass('active');$(document).find('.md-editor').each(function(){if($(this).attr('id')!==editor.attr('id')){var attachedMarkdown;if(attachedMarkdown=$(this).find('textarea').data('markdown'),attachedMarkdown===null){attachedMarkdown=$(this).find('div[data-provider="markdown-preview"]').data('markdown');}
if(attachedMarkdown){attachedMarkdown.blur();}}});options.onFocus(this);return this;},blur:function(e){var options=this.$options,isHideable=options.hideable,editor=this.$editor,editable=this.$editable;if(editor.hasClass('active')||this.$element.parent().length===0){editor.removeClass('active');if(isHideable){if(editable.el!==null){var oldElement=$('<'+editable.type+'/>'),content=this.getContent(),currentContent=this.parseContent(content);$(editable.attrKeys).each(function(k,v){oldElement.attr(editable.attrKeys[k],editable.attrValues[k]);});oldElement.html(currentContent);editor.replaceWith(oldElement);}else{editor.hide();}}
options.onBlur(this);}
return this;}};var old=$.fn.markdown;$.fn.markdown=function(option){return this.each(function(){var $this=$(this),data=$this.data('markdown'),options=typeof option=='object'&&option;if(!data)$this.data('markdown',(data=new Markdown(this,options)))})};$.fn.markdown.messages={};$.fn.markdown.defaults={autofocus:false,hideable:false,savable:false,width:'inherit',height:'inherit',resize:'none',iconlibrary:'glyph',language:'en',initialstate:'editor',parser:null,dropZoneOptions:null,buttons:[[{name:'groupFont',data:[{name:'cmdBold',hotkey:'Ctrl+B',title:'Bold',icon:{glyph:'glyphicon glyphicon-bold',fa:'fa fa-bold','fa-3':'icon-bold',octicons:'octicon octicon-bold'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent();if(selected.length===0){chunk=e.__localize('strong text');}else{chunk=selected.text;}
if(content.substr(selected.start-2,2)==='**'&&content.substr(selected.end,2)==='**'){e.setSelection(selected.start-2,selected.end+2);e.replaceSelection(chunk);cursor=selected.start-2;}else{e.replaceSelection('**'+chunk+'**');cursor=selected.start+2;}
e.setSelection(cursor,cursor+chunk.length);}},{name:'cmdItalic',title:'Italic',hotkey:'Ctrl+I',icon:{glyph:'glyphicon glyphicon-italic',fa:'fa fa-italic','fa-3':'icon-italic',octicons:'octicon octicon-italic'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent();if(selected.length===0){chunk=e.__localize('emphasized text');}else{chunk=selected.text;}
if(content.substr(selected.start-1,1)==='_'&&content.substr(selected.end,1)==='_'){e.setSelection(selected.start-1,selected.end+1);e.replaceSelection(chunk);cursor=selected.start-1;}else{e.replaceSelection('_'+chunk+'_');cursor=selected.start+1;}
e.setSelection(cursor,cursor+chunk.length);}},{name:'cmdHeading',title:'Heading',hotkey:'Ctrl+H',icon:{glyph:'glyphicon glyphicon-header',fa:'fa fa-header','fa-3':'icon-font',octicons:'octicon octicon-text-size'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent(),pointer,prevChar;if(selected.length===0){chunk=e.__localize('heading text');}else{chunk=selected.text+'\n';}
if((pointer=4,content.substr(selected.start-pointer,pointer)==='### ')||(pointer=3,content.substr(selected.start-pointer,pointer)==='###')){e.setSelection(selected.start-pointer,selected.end);e.replaceSelection(chunk);cursor=selected.start-pointer;}else if(selected.start>0&&(prevChar=content.substr(selected.start-1,1),!!prevChar&&prevChar!='\n')){e.replaceSelection('\n\n### '+chunk);cursor=selected.start+6;}else{e.replaceSelection('### '+chunk);cursor=selected.start+4;}
e.setSelection(cursor,cursor+chunk.length);}}]},{name:'groupLink',data:[{name:'cmdUrl',title:'URL/Link',hotkey:'Ctrl+L',icon:{glyph:'glyphicon glyphicon-link',fa:'fa fa-link','fa-3':'icon-link',octicons:'octicon octicon-link'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent(),link;if(selected.length===0){chunk=e.__localize('enter link description here');}else{chunk=selected.text;}
link=prompt(e.__localize('Insert Hyperlink'),'http://');var urlRegex=new RegExp('^((http|https)://|(mailto:)|(//))[a-z0-9]','i');if(link!==null&&link!==''&&link!=='http://'&&urlRegex.test(link)){var sanitizedLink=$('<div>'+link+'</div>').text();e.replaceSelection('['+chunk+']('+sanitizedLink+')');cursor=selected.start+1;e.setSelection(cursor,cursor+chunk.length);}}},{name:'cmdImage',title:'Image',hotkey:'Ctrl+G',icon:{glyph:'glyphicon glyphicon-picture',fa:'fa fa-picture-o','fa-3':'icon-picture',octicons:'octicon octicon-file-media'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent(),link;if(selected.length===0){chunk=e.__localize('enter image description here');}else{chunk=selected.text;}
link=prompt(e.__localize('Insert Image Hyperlink'),'http://');var urlRegex=new RegExp('^((http|https)://|(//))[a-z0-9]','i');if(link!==null&&link!==''&&link!=='http://'&&urlRegex.test(link)){var sanitizedLink=$('<div>'+link+'</div>').text();e.replaceSelection('!['+chunk+']('+sanitizedLink+' "'+e.__localize('enter image title here')+'")');cursor=selected.start+2;e.setNextTab(e.__localize('enter image title here'));e.setSelection(cursor,cursor+chunk.length);}}}]},{name:'groupMisc',data:[{name:'cmdList',hotkey:'Ctrl+U',title:'Unordered List',icon:{glyph:'glyphicon glyphicon-list',fa:'fa fa-list','fa-3':'icon-list-ul',octicons:'octicon octicon-list-unordered'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent();if(selected.length===0){chunk=e.__localize('list text here');e.replaceSelection('- '+chunk);cursor=selected.start+2;}else{if(selected.text.indexOf('\n')<0){chunk=selected.text;e.replaceSelection('- '+chunk);cursor=selected.start+2;}else{var list=[];list=selected.text.split('\n');chunk=list[0];$.each(list,function(k,v){list[k]='- '+v;});e.replaceSelection('\n\n'+list.join('\n'));cursor=selected.start+4;}}
e.setSelection(cursor,cursor+chunk.length);}},{name:'cmdListO',hotkey:'Ctrl+O',title:'Ordered List',icon:{glyph:'glyphicon glyphicon-th-list',fa:'fa fa-list-ol','fa-3':'icon-list-ol',octicons:'octicon octicon-list-ordered'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent();if(selected.length===0){chunk=e.__localize('list text here');e.replaceSelection('1. '+chunk);cursor=selected.start+3;}else{if(selected.text.indexOf('\n')<0){chunk=selected.text;e.replaceSelection('1. '+chunk);cursor=selected.start+3;}else{var list=[];list=selected.text.split('\n');chunk=list[0];$.each(list,function(k,v){list[k]='1. '+v;});e.replaceSelection('\n\n'+list.join('\n'));cursor=selected.start+5;}}
e.setSelection(cursor,cursor+chunk.length);}},{name:'cmdCode',hotkey:'Ctrl+K',title:'Code',icon:{glyph:'glyphicon glyphicon-asterisk',fa:'fa fa-code','fa-3':'icon-code',octicons:'octicon octicon-code'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent();if(selected.length===0){chunk=e.__localize('code text here');}else{chunk=selected.text;}
if(content.substr(selected.start-4,4)==='```\n'&&content.substr(selected.end,4)==='\n```'){e.setSelection(selected.start-4,selected.end+4);e.replaceSelection(chunk);cursor=selected.start-4;}else if(content.substr(selected.start-1,1)==='`'&&content.substr(selected.end,1)==='`'){e.setSelection(selected.start-1,selected.end+1);e.replaceSelection(chunk);cursor=selected.start-1;}else if(content.indexOf('\n')>-1){e.replaceSelection('```\n'+chunk+'\n```');cursor=selected.start+4;}else{e.replaceSelection('`'+chunk+'`');cursor=selected.start+1;}
e.setSelection(cursor,cursor+chunk.length);}},{name:'cmdQuote',hotkey:'Ctrl+Q',title:'Quote',icon:{glyph:'glyphicon glyphicon-comment',fa:'fa fa-quote-left','fa-3':'icon-quote-left',octicons:'octicon octicon-quote'},callback:function(e){var chunk,cursor,selected=e.getSelection(),content=e.getContent();if(selected.length===0){chunk=e.__localize('quote here');e.replaceSelection('> '+chunk);cursor=selected.start+2;}else{if(selected.text.indexOf('\n')<0){chunk=selected.text;e.replaceSelection('> '+chunk);cursor=selected.start+2;}else{var list=[];list=selected.text.split('\n');chunk=list[0];$.each(list,function(k,v){list[k]='> '+v;});e.replaceSelection('\n\n'+list.join('\n'));cursor=selected.start+4;}}
e.setSelection(cursor,cursor+chunk.length);}}]},{name:'groupUtil',data:[{name:'cmdPreview',toggle:true,hotkey:'Ctrl+P',title:'Preview',btnText:'Preview',btnClass:'btn btn-primary btn-sm',icon:{glyph:'glyphicon glyphicon-search',fa:'fa fa-search','fa-3':'icon-search',octicons:'octicon octicon-search'},callback:function(e){var isPreview=e.$isPreview,content;if(isPreview===false){e.showPreview();}else{e.hidePreview();}}}]}]],additionalButtons:[],reorderButtonGroups:[],hiddenButtons:[],disabledButtons:[],footer:'',fullscreen:{enable:true,icons:{fullscreenOn:{fa:'fa fa-expand',glyph:'glyphicon glyphicon-fullscreen','fa-3':'icon-resize-full',octicons:'octicon octicon-link-external'},fullscreenOff:{fa:'fa fa-compress',glyph:'glyphicon glyphicon-fullscreen','fa-3':'icon-resize-small',octicons:'octicon octicon-browser'}}},onShow:function(e){},onPreview:function(e){},onSave:function(e){},onBlur:function(e){},onFocus:function(e){},onChange:function(e){},onFullscreen:function(e){},onFullscreenExit:function(e){},onSelect:function(e){}};$.fn.markdown.Constructor=Markdown;$.fn.markdown.noConflict=function(){$.fn.markdown=old;return this;};var initMarkdown=function(el){var $this=el;if($this.data('markdown')){$this.data('markdown').showEditor();return;}
$this.markdown()};var blurNonFocused=function(e){var $activeElement=$(document.activeElement);$(document).find('.md-editor').each(function(){var $this=$(this),focused=$activeElement.closest('.md-editor')[0]===this,attachedMarkdown=$this.find('textarea').data('markdown')||$this.find('div[data-provider="markdown-preview"]').data('markdown');if(attachedMarkdown&&!focused){attachedMarkdown.blur();}})};$(document).on('click.markdown.data-api','[data-provide="markdown-editable"]',function(e){initMarkdown($(this));e.preventDefault();}).on('click focusin',function(e){blurNonFocused(e);}).ready(function(){$('textarea[data-provide="markdown"]').each(function(){initMarkdown($(this));})});}));
(function($){$.fn.markdown.messages.de={'Bold':"Fett",'Italic':"Kursiv",'Heading':"Überschrift",'URL/Link':"Link hinzufügen",'Image':"Bild hinzufügen",'Unordered List':"Unnummerierte Liste",'Ordered List':"Nummerierte Liste",'Code':"Quelltext",'Quote':"Zitat",'Preview':"Vorschau",'strong text':"Sehr betonter Text",'emphasized text':"Betonter Text",'heading text':"Überschrift Text",'enter link description here':"Linkbeschreibung",'Insert Hyperlink':"URL",'enter image description here':"Bildbeschreibung",'Insert Image Hyperlink':"Bild-URL",'enter image title here':"Titel des Bildes",'list text here':"Aufzählungs-Text"};}(jQuery));