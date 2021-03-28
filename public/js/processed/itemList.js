var table=null,colSource=0,colName=1,colPrios=2,colWishlist=3,colNotes=4,colPriority=5,lastSource=null;function createTable(a){return memberTable=$("#itemTable").DataTable({autoWidth:!1,data:items,columns:[{title:'<span class="fas fa-fw fa-skull-crossbones"></span> Boss',data:"",render:function render(a,t,n){return n.source_name&&(thisSource=n.source_name),'\n                    <ul class="no-bullet no-indent mb-0">\n                        '.concat(n.source_name?'\n                            <li>\n                                <span class="font-weight-bold">\n                                    '.concat(n.source_name,"\n                                </span>\n                            </li>"):"","\n                    </ul>")},visible:!0,width:"130px",className:"text-right"},{title:'<span class="fas fa-fw fa-sack text-success"></span> Loot',data:"",render:function render(a,t,n){var e='data-wowhead-link="https://'.concat(wowheadSubdomain,".wowhead.com/item=").concat(n.item_id,'"\n                        data-wowhead="item=').concat(n.item_id,"?domain=").concat(wowheadSubdomain,'"'),i="";return i=guild?"/".concat(guild.id,"/").concat(guild.slug,"/i/").concat(n.item_id,"/").concat(slug(n.name)):"",'\n                    <ul class="no-bullet no-indent mb-0">\n                        <li>\n                            <a href="'.concat(i,'"\n                                class="').concat(n.quality?"q"+n.quality:"",'"\n                                ').concat(e,">\n                                ").concat(n.name,"\n                            </a>\n                        </li>\n\n                        ").concat(n.is_bis||n.is_bis_horde||n.is_bis_alliance?"\n                            <li>\n                                <small>\n                                    ".concat(n.is_bis?"BIS":"","\n                                    ").concat(n.is_bis_alliance?'<span class="text-shaman">A</span>':"","\n                                    ").concat(n.is_bis_horde?'<span class="text-dk">H</span>':"","\n                                </small>\n                            </li>"):"","\n                    </ul>")},visible:!0,width:"330px"},{title:'<span class="fas fa-fw fa-sort-amount-down text-gold"></span> Prio\'s',data:"priod_characters",render:function render(a,t,n){return a&&a.length?getCharacterList(a,"prio",n.item_id):"—"},orderable:!1,visible:!!showPrios,width:"300px"},{title:'<span class="text-legendary fas fa-fw fa-scroll-old"></span> Wishlist',data:"wishlist_characters",render:function render(a,t,n){return a&&a.length?getCharacterList(a,"wishlist",n.item_id):"—"},orderable:!1,visible:!!showWishlist,width:"400px"},{title:'<span class="fas fa-fw fa-comment-alt-lines"></span> Notes',data:"guild_note",render:function render(a,t,n){return a?'<span class="js-markdown-inline">'.concat(nl2br(a),"</span>"):"—"},orderable:!1,visible:!!showNotes,width:"200px"},{title:'<span class="fas fa-fw fa-comment-alt-lines"></span> Prio Notes',data:"guild_priority",render:function render(a,t,n){return a?'<span class="js-markdown-inline">'.concat(nl2br(a),"</span>"):"—"},orderable:!1,visible:!!showNotes,width:"200px"}],order:[],paging:!1,fixedHeader:!0,initComplete:function initComplete(){makeWowheadLinks(),parseMarkdown()},createdRow:function createdRow(t,n,e){0!=e&&null!=a||(a=n.source_name),n.source_name!=a&&($(t).addClass("top-border"),a=n.source_name)}}),memberTable}function getCharacterList(a,t,n){var e='<ul class="list-inline js-item-list mb-0" data-type="'.concat(t,'" data-id="').concat(n,'">'),i=4,s=null;return $.each(a,function(a,n){"prio"==t&&n.pivot.raid_id&&n.pivot.raid_id!=s&&(s=n.pivot.raid_id,e+='\n                <li data-raid-id="" class="js-item-wishlist-character no-bullet font-weight-normal font-italic  text-muted small">\n                    '.concat(n.raid_name?n.raid_name:"","\n                </li>\n            ")),e+='\n            <li data-raid-id="'.concat("prio"==t?n.pivot.raid_id:n.raid_id,'"\n                value="').concat("prio"==t?n.pivot.order:"",'"\n                class="js-item-wishlist-character list-inline-item font-weight-normal mb-1 mr-0 ').concat(n.pivot.received_at?"font-strikethrough":"",'">\n                <a href="/').concat(guild.id,"/").concat(guild.slug,"/c/").concat(n.id,"/").concat(n.slug,'"\n                    title="').concat(n.raid_name?n.raid_name+" -":""," ").concat(n.level?n.level:""," ").concat(n.race?n.race:""," ").concat(n.spec?n.spec:""," ").concat(n.class?n.class:""," ").concat(n.username?"("+n.username+")":"",'"\n                    class="text-').concat(n.class?n.class.toLowerCase():"",'-important tag d-inline">\n                    <span class="text-muted">').concat(n.pivot.order?n.pivot.order:"",'</span>\n                    <span class="role-circle" style="background-color:').concat(getColorFromDec(n.raid_color),'"></span>').concat(n.name,"\n                    ").concat(n.is_alt?'\n                        <span class="text-legendary">alt</span>\n                    ':"",'\n                    <span class="js-watchable-timestamp smaller text-muted"\n                        data-timestamp="').concat(n.pivot.created_at,'"\n                        data-is-short="1">\n                    </span>\n                </a>\n            </li>')}),e+="</ul>"}$(document).ready(function(){table=createTable(),$(".toggle-column").click(function(a){a.preventDefault();var t=table.column($(this).attr("data-column"));t.visible(!t.visible())}),$(".toggle-column-default").click(function(a){a.preventDefault(),table.column(colName).visible(!0),table.column(colWishlist).visible(!0),table.column(colPrios).visible(!0),table.column(colNotes).visible(!0),table.column(colPriority).visible(!0)}),table.on("column-visibility.dt",function(a,t,n,e){makeWowheadLinks(),trackTimestamps(),parseMarkdown()}),$("#raid_filter").on("change",function(){var a=$(this).val();a?($(".js-item-wishlist-character[data-raid-id!='"+a+"']").hide(),$(".js-item-wishlist-character[data-raid-id='"+a+"']").show()):$(".js-item-wishlist-character").show()}).change(),trackTimestamps()});
