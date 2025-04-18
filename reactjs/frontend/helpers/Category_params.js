import React from 'react';
import ItemGroup from './itemGroup'
import Action_buttons from './Action_buttons'
import PanelCollapsible from './PanelCollapsible'
import Select from './Select'
import { getSelectValue, sendToServer } from './support_functions'
import SelectGroup from './selectGroup'


export class Shop_category extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const { title = '', value = '' } = this.props.item,
          { func, optMap }= this.props;
    return (
        <div className = 'row'>
            <div className = 'col-md-6'>
                <SelectGroup
                    search = {true}
                    label = {title}
                    value = {value}
                    list = {optMap || [{label:'---',value:''}]}
                    func = {func}
                    />
            </div>
        </div>
        );
    }
}

export class Category_data extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
      const {
        siteid,
        UpdateCats,
        defaults_actions,
        category_params,
        saved_params,
        id:saved_id,
        save
    } = this.props;

    let update_category = this.props.update_category || (()=>{})
    let sp_update = this.props.sp_update || (()=>{})

    let category_key = ['category','category2'];
    return (
        <div className='row'>
            {category_key.map((title,idx)=>{
                return (
                    <Category_block
                        key = {idx}
                        item = {{
                            title,
                            data: category_params[title],
                            category: saved_params[title],
                            description:category_params[title+'_name'],
                            log:category_params[title+'_log'],
                            saved_id
                        }}
                        siteid = {siteid}
                        save = {save}
                        update_category = {update_category.bind(this,title+'_id')}
                        sp_update = {sp_update.bind(this,title)}
                        UpdateCats = {UpdateCats}
                        defaults_actions = {defaults_actions}
                    />
                )
            })}
        </div>
    );
  }
}

export class Category_block extends React.Component {
    constructor(props) {
        super(props);
    }
    category_id_changed(id){
        var category_name = this.props.item.title;
        this.props.defaults_actions.showConfirm({
            mess:'If you agree to change category number, data will be saved, and block will be reload',
            type:'info',
            resolve:()=>{
                this.props.defaults_actions.generateSimpleAction(
                    'EBAY_CATALOGUE_UPDATE_FLD',
                    ['saved_params'],
                    category_name,
                    id
                );
                this.props.save();
            }
        })


    }
    openCategoryFrame(title,siteid,e){
        window.selected = (function(value){
            const {
                saved_id
            } = this.props.item || {};
            if(saved_id){
                this.category_id_changed(value);
            }else{
                this.props.update_category(value);
            }
        }).bind(this);
        window.open('/selcat.php?input='+title+'&siteid='+siteid, 'sd', 'width=400,height=400');
        e.preventDefault();
    }
    render() {
        const {
            title = '',
            description = '',
            log = [],
            category,
            data = [],
            saved_id,
        } = this.props.item,
        {
            siteid,
            defaults_actions,
            UpdateCats
        } = this.props;
        return (
            <div className = 'col-md-6'>
                <div className = 'row'>
                    <div className = 'col-md-12 col-xs-12'>
                        <ItemGroup
                            label = {title}
                            type = 'number'
                            bsClass = 'select_cat'
                            iValue = {category}
                            placeholder = 'Insert price...'
                            func = {()=>{}}
                            btn_label = 'select'
                            btn_func = {this.openCategoryFrame.bind(this,title,siteid)}
                            />
                    </div>
                </div>
                <div>{description||<br/>}</div>
                {log.length ?
                    <PanelCollapsible title='log' label = {title}>
                        <ul className='support_ul'>
                            {log.map((item ,idx)=>{
                                return (
                                    <li key = {idx}>{item}</li>
                                )
                            })}
                        </ul>
                    </PanelCollapsible>:''
                }

            {category && saved_id?
            <Category_param
                UpdateCats = {UpdateCats}
                defaults_actions = {defaults_actions}
                new_field = {true}
                category = {category}
                page_saved_id={saved_id}
            />:''}
            {data.map((item,idx)=>{
                return (
                    <Category_param
                        UpdateCats = {UpdateCats}
                        key = {idx}
                        defaults_actions = {defaults_actions}
                        {...item}
                        sp_update = {this.props.sp_update}
                        category_name = {title}
                        category = {category}
                        page_saved_id={saved_id}
                    />
                )
            })}
            </div>
        );
    }
}

export class Category_param extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            new_property_title: '',
            new_property_value: ''
        }
    }
    clear_params(){
        this.setState({
            new_property_title: '',
            new_property_value: ''
        });
    }
    change_new_property_params(param,value){
        this.setState({ [param]:value });
    }
    saveCustomSP(title,value){
        var  UpdateCats = this.props.UpdateCats.bind(this),
             clear_params = this.clear_params.bind(this),
             page_saved_id = this.props.page_saved_id;

        sendToServer({
            'fn': 'saveCustomSP',
            'name': title,
            'value': value,
            'saved_id': this.props.page_saved_id,
            'categoryid': this.props.category
        }, '', ()=>{
                clear_params();
                UpdateCats(page_saved_id,true);
            })
    }
    render() {
        const {
            Name = '',
            new_field,
            SelectionMode,
            deleted = '0',
            page_saved_id
        } = this.props,
        {
            new_property_title = '',
            new_property_value = ''
        } = this.state;
        return (
            <div className='row'>
                <div className='col-md-12'>
                    {
                        !new_field ?
                        <dl className={'clearfix '+(SelectionMode == 'SelectionOnly' ? 'bg-success': 'bg-warning')} style={{padding:'4px'}}>
                            <dt>
                                <span className='pull-left '>{Name}</span>
                                {page_saved_id ?
                                    <Category_property_control_btn {...this.props}/> : ''
                                }
                            </dt>
                            <dd >
                                {!deleted || deleted == '0' ? <SelectionModeFactory {...this.props}/> :''}
                            </dd>

                        </dl>
                        :
                        <div className = 'bg-danger' style = {{padding:'5px'}}>
                            <label>Add new SP</label>
                            <ItemGroup
                                label = 'Title'
                                iValue = {new_property_title}
                                func = {this.change_new_property_params.bind(this,'new_property_title')}
                                />
                            <ItemGroup
                                label = 'Value'
                                iValue = {new_property_value}
                                func = {this.change_new_property_params.bind(this,'new_property_value')}
                                />
                            <Action_buttons
                                buttons = {
                                    [
                                        {
                                            title:'add',
                                            click:this.saveCustomSP.bind(this,new_property_title,new_property_value)
                                        }
                                    ]
                                }
                                />
                        </div>
                    }
                </div>
            </div>
        );
    }
}

export  class Category_property_control_btn extends React.Component {
    constructor(props) {
        super(props);
    }
    deleteCustomSP(categoryid, saved_id, name_id){

        sendToServer(
            {
                'fn': 'deleteCustomSP',
                'name_id': name_id,
                'saved_id': saved_id,
                'categoryid': categoryid
            }, '', ()=> {
                this.props.defaults_actions.generateSimpleAction.bind(
                    this,
                    'EBAY_CATALOGUE_PROPERTY_DELETE',
                    this.props.category_name,
                    name_id
                )
            })
    }
    deleteSP(categoryid, saved_id, name_id, action){
        sendToServer(
            {
                'fn': 'deleteSP',
                'name_id': name_id,
                'saved_id': saved_id,
                'categoryid': categoryid,
                'action': action
            }, '',  ()=> {
                this.props.defaults_actions.generateSimpleAction(
                    'EBAY_CATALOGUE_PROPERTY_UPDATE',
                    [this.props.category_name,name_id],
                    'deleted',
                    action ? '1' : null
                )
            })
    }
    render() {
        const {
            category,
            saved_id,
            deleted,
            page_saved_id,
            id:property_id
        } = this.props,
        deleteCustomSP = this.deleteCustomSP.bind(this,category,page_saved_id,property_id),
        deleteSP = this.deleteSP.bind(this,category,page_saved_id,property_id);

        let button = {};
        if(saved_id){
            button['func'] = deleteCustomSP;
            button['bsClass'] = 'glyphicon glyphicon-trash';
        }else{
            if(!deleted){
                button['func'] = deleteSP.bind(this,1);
                button['bsClass'] = 'glyphicon glyphicon-remove'
            }else{
                button['func'] = deleteSP.bind(this,0);
                button['bsClass'] = 'glyphicon glyphicon-repeat'
            }
        }
        return (
            <button
                onClick={button.func}
                className='close pull-left'>
                <small className = {button.bsClass}></small>
            </button>
        );
    }
}

export  class SelectionModeFactory extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            new_select_value:''
        }
    }
    update_factory(value){
        var category_name = this.props.category_name,
            id = this.props.id;

        this.props.defaults_actions.generateSimpleAction(
            'EBAY_CATALOGUE_PROPERTY_UPDATE',
            [category_name,id],
            'data',
            value
        )
    }
    send_value_by_property_to_server(sp_id,saved_id,value,key){
        sendToServer( { 'fn': 'saveSP', sp_id, 'id': key || 0, saved_id, value } ,'',null)
    }
    select_handler(max,value){
        var res,
            sp_id = this.props.id,
            saved_id = this.props.page_saved_id;
        if(Number(max) > 1){
            res = {};
            value.forEach((item)=>{
                res[item] = item;
            })
        }else{
            res = [value]
        }
        if(saved_id){
            this.update_factory.call(this,res)
            this.send_value_by_property_to_server(sp_id,saved_id,res)
        }else{
            this.props.sp_update({sp_id,value,fld:'select'});
        }
    }
    input_handler(value,key){
        var sp_id = this.props.id,
            saved_id = this.props.page_saved_id;
        if(saved_id){
            this.send_value_by_property_to_server(sp_id,saved_id,value,key)
            if(key){
                this.change_new_property('');
                this.props.UpdateCats(saved_id,true);
            }
        }
    }
    change_new_property(new_select_value){
        this.setState({new_select_value})
    }
    render() {
        const {
            SelectionMode,
            Values = [],
            MaxValues = '1',
            MultiValues = [],
            data,
            page_saved_id
        } = this.props,
        { new_select_value } =this.state || {},
        update_factory = this.update_factory.bind(this);
        let comp = (<div></div>),
            options = Values,
            value = data || [];

        if(!Array.isArray(value)){
            value = Object.keys(value);
        }

        value = Number(MaxValues)>1 ? value : value[0];
        switch (SelectionMode) {
            case 'SelectionOnly':
                comp = (
                    <div className='row'>
                        <div className='col-md-12'>
                            <Select
                                search={true}
                                value={value}
                                multi = {Number(MaxValues)>1}
                                multi_v = {Number(MaxValues)>1}
                                optionsMap={getSelectValue(options)}
                                onChangeSelect={this.select_handler.bind(this,MaxValues)}
                            />
                        </div>
                    </div>
                )
            break;
            case 'FreeText':
                if(Object.keys(Values).length){
                    comp = (
                        <div >
                            <div className='row'>
                                <div className='col-md-12'>
                                    <Select
                                        search={true}
                                        value={value}
                                        multi = {Number(MaxValues)>1}
                                        multi_v = {Number(MaxValues)>1}
                                        optionsMap={getSelectValue(options)}
                                        onChangeSelect={this.select_handler.bind(this,MaxValues)}
                                    />
                                </div>
                                {MultiValues.length ?
                                    <div className='col-md-12'>
                                        <Select
                                            search={true}
                                            value={''}
                                            multi = {true}
                                            multi_v = {true}
                                            optionsMap={getSelectValue(MultiValues)}
                                            onChangeSelect={this.select_handler.bind(this,2)}
                                        />
                                </div>:''
                                }
                            </div>
                            { page_saved_id ?
                                <div className='row'>
                                    <label className = 'col-md-5'>
                                        additional own description
                                    </label>
                                    <div className = 'col-md-7'>
                                        <input
                                            className='form-control'
                                            type='text'
                                            style={{height:'24px'}}
                                            value = {new_select_value}
                                            onBlur = {(e)=>{this.input_handler([e.target.value],e.target.value)}}
                                            onChange = {(e)=>{
                                                this.change_new_property(e.target.value)
                                            }}
                                            />
                                    </div>
                                </div>:''
                            }

                        </div>
                    )
                }else{
                    comp = (
                        <div className='row'>
                            <div className = 'col-md-12'>
                                <input
                                    className='form-control'
                                    type='text'
                                    style={{height:'24px'}}
                                    value = {value}
                                    onBlur = {(e)=>{this.input_handler([e.target.value])}}
                                    onChange = {(e)=>{
                                        let value = e.target.value;
                                        if(page_saved_id){
                                            update_factory([value]);
                                        }else{
                                            this.props.sp_update({sp_id:this.props.id,value, fld:'input'});
                                        }
                                    }}
                                    />
                            </div>
                        </div>
                    )
                }


            break;
            case 'Prefilled':
            break;
        }
        return comp
    }
}
