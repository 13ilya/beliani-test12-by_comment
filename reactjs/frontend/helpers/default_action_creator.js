import {sendToServer, SaveOnServer,spinShow} from './support_functions'
export default (dispatch,functions_stack,url,name) => {

    function ACTIONS(){
        this._type = '';
        this._url = url || '';
        this._data = {};
        this._name = name || '';
        this.params_initialize = function(params){
            var params_list = params || {};
            this._type = params_list.type || 'GET';
            this._url = params_list.url || this._url;
            this._data = params_list.data || {};
            this._name = params_list.name || this._name;
            this._silent = params_list.silent || this._silent;
            this._func = params_list.func || null;
        }
        this.fetch = (params) => {
            if(!functions_stack.fetchFromServer) return false;
            this.params_initialize(params);
            functions_stack.fetchFromServer(this._type, this._url, this._data, this._name, dispatch, this._func);
        };
        this.save = (params) => {
            if(!functions_stack.SaveBlock) return false;
            this.params_initialize(params);
            functions_stack.SaveBlock(dispatch, this._data, this._name,this._url,this._silent);
        };
        this.filtersFetch = (filters,name) => {
            this.fetch({
                url:'/api/filtersOptions/',
                data:{type:filters},
                name
            })
        };
        this.filterFieldChange = (fld,value) => {dispatch({type:'FILTER_CHANGE',value,fld})};
        this.filterDefaults = (params) => {dispatch({type:'FILTER_DEFAULTS',params})};

        this.generateSimpleAction = (action,block,fld,value) => { dispatch({type:action.toUpperCase(),value,fld,block})
        };

        this.showAlert = (params) =>{
            var mess = params.mess || 'ok',
                type = params.type || 'success',
                name = params.name || 'default_name';
            dispatch({
                type: 'ALERT',
                alertKey: name,
                alertType: type,
                text: mess,
                show: true
            })
        }
        this.showConfirm = (params) =>{
            var mess = params.mess || 'ok',
                type = params.type || 'success',
                reject = params.reject,
                resolve = params.resolve;

            dispatch({
                type: 'CONFIRM',
                confirmType: type,
                text: mess,
                callback :{reject , resolve}
            })
        }
        this.create_issue = (data,files) =>{
            spinShow(true,dispatch);
            sendToServer ({...data},'/js_backend.php',(result)=>{
                console.log('result',result);
                if(!result['res2']){
                    this.showAlert = ({
                        mess:'Something wrong, the Issue did not created',
                        type:'danger'
                    })
                    spinShow(false,dispatch);
                    return false;
                }
                var data = {
                    page_id : result['res2']
                }
                var self = this;
                if(files.length){
                    this.fileLoad('/api/issueLog/saveImages/',data,files,()=>{
                        self.showAlert({
                            mess:'Issue #'+data.page_id+' created successfull!',
                            type:'success'
                        })
                        open('/react/logs/issue_logs/'+data.page_id+'/');
                        spinShow(false,dispatch);
                    })
                }else{
                    open('/react/logs/issue_logs/'+data.page_id+'/');
                    spinShow(false,dispatch);
                }
            })
        }
        this.fileLoad = (url, data, files, func,file_field) => {
            file_field = file_field ? file_field+'[]'  : 'imgs[]'
            spinShow(true,dispatch);
            let inputFile = [];
            if(Array.isArray(files)){
                inputFile = files
            }else{
                for(let key = 0;key < files.length;key++){
                    inputFile.push(files[key]);
                }
            }
            var fd = new FormData,
                callback = (data)=>{
                    spinShow(false,dispatch);
                    func(data);
                };
            console.log('inputFile',inputFile);
            if(inputFile.length){
                inputFile.forEach((value)=>{
                    fd.append(file_field, value);
                })
                fd.append('data',JSON.stringify(data));
                console.log('fd',fd);
                SaveOnServer(fd,url,callback,true)
            }
        }
    }
    return new ACTIONS
}
