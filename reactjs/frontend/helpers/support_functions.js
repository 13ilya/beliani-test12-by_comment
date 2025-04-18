import JsHttpRequest from '../js/jsRequest'
export function goToTop(){
    $('html, body').animate({scrollTop: 0}, 500);
}
export function set_value_to_the_reduce_state(state,action){
    let cursor = state;
    action.block.forEach((value) => {
        cursor = cursor[value] || cursor;
    });

    cursor[action.fld] = action.value;
    return state
}
export function filter_list(filters_stack,obj){
    let result = obj;
    for(let key in filters_stack){
        let value = filters_stack[key];
        if (value == '-1') continue;
        result = _.filter(result,(item) => {
            if(Array.isArray(item[key])){
                if (item[key].indexOf(value) != -1) return item;
            }else if(typeof item[key] == 'object'){
                if(item[key][value]) return  item[key][value];
            }else{
                return item[key] == value;
            }
        })
    }
    return result;
}
export function getPreferValueFromMulang(langs){
    var langStack = _.filter(langs, item => item.isActive),
        mainLang = _.find(langs,lang => lang.main);

    if(!mainLang){
        mainLang = langStack[0] || {};
    }
    const lang = mainLang.title;
    return function(mulang){
        let mainLangValue = mulang[lang] ? mulang[lang].value : '',
            englishValue = mulang['english'] ? mulang['english'].value : '',
            germanValue =  mulang['german'] ? mulang['german'].value : '',
            resLang = '',
            value = '';

        if(mainLangValue){
            resLang = lang;
            value = mainLangValue;
        }else if(englishValue){
            resLang = 'english';
            value = englishValue;
        }else if(germanValue){
            resLang = 'german';
            value = germanValue;
        }else{
            for(let key_1 in mulang){
                if(mulang[key_1].value && !value){
                    value = mulang[key_1].value;
                    resLang = key_1;
                }
            }
        }

        return {value: value, lang: resLang ? 'found on '+ resLang : '' };
    }
}
export function getSelectValue (obj, label, value, no_sort) {
    /**generate collection of objects with 2 params {label,value} for select component with sort by label**/
    var arr = []
    let tmp = {},_label,_value
    for (var key in obj){
        let tmp_val = Array.isArray(obj[key])?obj[key][0]:obj[key];
        _label =!label? tmp_val : tmp_val[label];
        _value =!value? key : tmp_val[value];
        arr.push({label: _label.trim().replace(/\s+/gi,' '),value: _value})
    }
    tmp = _.sortBy(arr, function(o) { return o.label; });
    return !no_sort ? tmp : arr;
}

export function sort_by_column(obj,column_param,numeric){
    let result = {};
    result = _.sortBy(obj,(item)=>{
        var value = item[column_param];
        if(value && (column_param == 'id' || numeric)){
            value = Number(value.replace(/\./gi,'').replace(/\,/gi,'.'))
        }
        return value
    })

    return result || obj;
}

export function filter_by(params_stack,obj){
    var stak_for_filters = {};
    for(let key in params_stack){
        if(key === 'active_column') continue;
        if(params_stack[key] !='') stak_for_filters[key] = params_stack[key];
    }
    return Object.keys(stak_for_filters).length ? _.filter(obj,stak_for_filters) : obj;
}
export function getDateNow(only,slash){
    var d = new Date();
    if(slash){
        for(let key in slash){
            switch (key) {
                case 'year':
                    d.setFullYear(d.getFullYear()+slash[key]);
                break;
                case 'mounth':
                    d.setMonth(d.getMonth()+slash[key]);
                break;
                case 'day':
                    d.setDate(d.getDate()+slash[key]);
                break;
            }
        }
    }
    d = d.toISOString();
    var date = d.match(/([0-9]+\-[0-9]+\-[0-9]+)/gi);
    var time = d.match(/([0-9]+:[0-9]+:[0-9]+)/gi);
    date +=!only?' '+time:'';
    return date;
}
export function updateMasterComment(id,dispatch){ // fix later
    let comment = getCookie(id,dispatch);
    if(!comment){
        const url = '/api/condensedSA/get/?id='+id+'&block=saved_params';
        const data = {url,method: 'GET'}
        jQuery.ajax({
          method: 'get',
          url: url,
          data: data,
          success: (data) => {
              comment = data.sa.saved_params.auction_name;
              setCookie(id,comment)
              dispatch({type:'FETCH_SA_PAGE_Comment',comment})
          }
        })
    }
    dispatch({type:'FETCH_SA_PAGE_Comment',comment})
}
export function getAllChildren(list,id, inactive){
    var search_block = {
        parent_id:id
    }
    inactive ? search_block['inactive'] = inactive : false;
    console.log('search_block',search_block);
    var part = _.partition(list,search_block)[0];
    if(part.length)
        part.forEach((value)=>{
            value.children = getAllChildren(list,value.id,inactive);
        })
    return part;
}
export function SaveOnServer (data,url,func,as_formData) {
    console.log('Saving ', name, data)
    var ajax_data = {
        method: 'POST',
        url,
        data,
        success: (data)=>{
            func ? func(data) : console.log(data);
        }
    }
    if (as_formData){
        ajax_data['processData'] = false;
        ajax_data['contentType'] = false;
    }
    jQuery.ajax(ajax_data);
}

export function getCookie(name) {     //get cookies from browser
  var matches = document.cookie.match(new RegExp(
    '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'
  ));
  return matches ? decodeURIComponent(matches[1]) : undefined;
}
export function setCookie(name, value) {
    let date = new Date(new Date().getTime() + 60 * 1000000000);
    value = encodeURIComponent(value);
    document.cookie = name+'='+value+'; path=/; expires=' + date.toUTCString();
}
function absParam(x,fparam){
    return (100*x)/fparam
}
export function HTMLtoAbsolute(text,sizes,fsize){ /**fix it**/
    text = text.trim();
    const width = sizes.shop_img_width;
    const height = sizes.shop_img_height;
    const arr = text.match(/[a-zA-Z\-]+:[0-9]+(?=px)/gi)
    let res = text
    if(arr)
        arr.forEach((value)=>{
            let val = value.split(':')
            let key = val[0].toLowerCase();
            let vparam = val[1];
            if(key=='width' || key == 'left' || key == 'right'){
                res=res.replace(value+'px',key+':'+absParam(vparam,width)+'%')
            }
            else if(key == 'height' || key == 'top' || key == 'bottom'){
                res=res.replace(value+'px',key+':'+absParam(vparam,height)+'%')
            }
            else if(value.indexOf('font')!=-1){
                res=res.replace(value+'px',key+':'+absParam(vparam,fsize)+'%')
            }
        })
    res=res.replace(/px/gi,'%')
    return res;
}
export function translate(text){
    /**Translate titles value to alias value on sa page**/
    text = text.toLowerCase();
    text = text.trim();
    text = text.replace(/[®',()\+\@\–\-\--\---\s]+/gi,'-');
    text = text.replace(/[\u00A0-\u00BF]/ig,'-')
    text = text.replace(/[\u02B0-\u036F]/ig,'-')
    text = text.replace(/[\u0021-\u002F]/ig,'-')
    text = text.replace(/[\u003A-\u003F]/ig,'-')
    text = text.replace(/[\u00D9-\u00DC]/ig,'u')
    text = text.replace(/[\u00C0-\u00C5]/ig,'a')
    text = text.replace(/[\u00C8-\u00CB]/ig,'e')
    text = text.replace(/[\u00D2-\u00D6]/ig,'o')
    text = text.replace(/[\u0106-\u010D]/ig,'c')
    text = text.replace(/[\u00D1]/ig,'n')
    text = text.replace(/(ś)+/gi,'s');
    text = text.replace(/(ć|ç|ĉ|ƈ)+/gi,'c');
    text = text.replace(/(æ)+/gi,'e');
    text = text.replace(/(ł)+/gi,'l');
    text = text.replace(/[\u00CC-\u00CF]/ig,'i')
    text = text.replace(/(ź|ż)+/gi,'z');
    return text;
}
export function spinShow (visible, dispatch) {
    dispatch({
        type: 'SPINNER',
        _spinnerStopped: !visible
    })
}
export function openWin (src, name) {
    /**open new wnd with custom data**/
    let myWin = open('', 'displayWindow' + name, '')
    myWin.document.open()
    myWin.document.write('<html><head><title>' + name +
        '</title></head><body><div class="inner">' + src + '</div></body></html>'
    )
    return myWin
}
export function sendToServer (data, url, callBack) {
    /**added for support old sa logic**/

  JsHttpRequest.query(url || '/js_backend.php',{...data },
    function (result, error) {
      if (callBack !== null) callBack(result, error)
    }, true
  )
}
export function fetchFromApi(data, url, callBack){
    /****/
    jQuery.ajax({
      method: 'get',
      url,
      data,
      success: (data) => {callBack(data)}
    })
}
export function recacheImages(arr){
    /**used in SA image page after drop and upload images**/
    jQuery.ajax({
      method: 'get',
      url: '/api/condensedSA/recachePics/',
      data: {doc_id:arr},
      success: (data) => {console.log(data)}
    })
}
export function copyObj (source) {
    /**Function for duplicate object**/
  if (source == 0) return ''
  let target = {}
  let type = ''
  for (let key in source) {
    type = typeof (source[key])
    if ((type == 'object' || type == 'array') && source[key] != null)
      target[key] = copyObj(source[key])
    else {
      target[key] = source[key]
    }
  }
  return target
}
export function sortOrdering (data,param) {
    /**Function for sorting items ids by ordering**/
  let key
  let order = []
  let res = []
  for (key in data) {
    order.push(Number(data[key][param || 'ordering']))
  }
  order = order.sort(function (a, b) {
    return a - b
  })
  order.forEach((idx) => {
    for (key in data) {
      if (data[key][param || 'ordering'] == null) data[key][param || 'ordering'] = '0'
      if (data[key][param || 'ordering'] == idx) {
        if (res.indexOf(key) == -1) res.push(key)
      }
    }
  })
  return res
}
export function RowCheck (data, fld, value) {
  for (let key in data)
    if (data[key][fld] == value) {
      alert('This item already exist')
      return false
  }
  return true
}
