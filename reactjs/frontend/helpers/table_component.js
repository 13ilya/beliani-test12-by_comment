import React from 'react'
import { sort_by_column , goToTop} from './support_functions'
import Select from './Select'
import { Link } from 'react-router'
export default class Table_component extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            page_step:0
        }
        this.update_page_step = this.update_page_step.bind(this)
    }

    update_page_step(value){
        goToTop();
        this.setState({
            page_step:value
        })
    }
    _is_link(str){
        if(!str) return ''
        var pattern = /(https?:\/\/)?([\w.-]?\.?[\w.-]+\.[\w]{2,6}\S*)/gim,
            urls = str.match(pattern),
            parsed_text = str.replace(/\n/gi,'<br/>');

        if(!urls) return parsed_text;
        urls.forEach((value)=>{
            parsed_text = parsed_text.replace(value,'<a target = "_blank" href="'+value+'">'+ value +'</a>')
        });

        return parsed_text;
    }

    getPagination(values,pagination){
        if(!pagination) return [];
        var quantity = values.length,
            pages = Math.floor(quantity / pagination) + Number(quantity % pagination != 0),
            pages_array = [];

        for(let page_step = 0; page_step < pages; page_step++){

            let bsClass = this.state.page_step == page_step ? 'active ' : '';

            bsClass = page_step == 0 || page_step ==  pages ? 'disabled' : ''

            pages_array.push(
                <li
                    key={'pagination'+page_step}>
                        <a  className = {bsClass}
                            onClick = {this.update_page_step.bind(this,page_step)}
                            >{page_step+1}</a>
                </li>);
        }
        return pages_array;
    }

    render() {
        const
            {
                stack = [],
                bsClass = '',
                active_column = {},
                filterFieldChange,
                values = [],
                tr_click = null,
                pagination = false,
                block_under_table = '',
                mobile_tr:{
                    focus_id = '',
                    tr_class = [],
                    offset = 0,
                } = {}
            } = this.props,
            pages = this.getPagination(values,pagination),
            step = this.state.page_step*pagination;
            let result_values = !pages || pages.length <= 1? values : values.slice(step,step + pagination+1);

        let issue_list_sorted = sort_by_column(result_values, active_column.name,active_column.numeric);
        issue_list_sorted = active_column.reverce ? issue_list_sorted.reverse() : issue_list_sorted;
        return (
            <div>
                <table className={'table table-bordered '+bsClass}>
                    <thead>
                        <tr className='table-header'>
                            {stack.map((header_item,idx)=>{
                                var is_active = ( active_column.name == header_item.param ),
                                    hovered = !header_item.no_sortable ? 'hovered ': '';
                                return (
                                    <td
                                        key = {idx}
                                        style = {{width:header_item.width+'px',minWidth:header_item.width+'px'}}
                                        className={hovered + ( is_active ? 'active' : '' )}
                                        colSpan = {header_item.colspan || ''}
                                        onClick ={!header_item.no_sortable ?
                                            filterFieldChange.bind(
                                                null,
                                                'active_column',
                                                {
                                                    name:header_item.param,
                                                    numeric: header_item.numeric || false,
                                                    reverce: active_column.reverce!=undefined ? !active_column.reverce : false
                                                }
                                            ):()=>{}
                                        }
                                        >
                                        {header_item.title}
                                    </td>
                                )
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {
                            issue_list_sorted.map((row,idx1)=>{
                                let bs_tr_class = '';

                                if(focus_id != ''){
                                    bs_tr_class = focus_id == idx1 + offset ? tr_class[0] : tr_class[1]
                                }
                                return (
                                    <tr key={idx1}
                                        className = {bs_tr_class}
                                        onClick = {()=>{tr_click ? tr_click(row) : false}}
                                        >
                                        {stack.map((stack,idx)=>{
                                            let cell_value;
                                            switch (stack.component) {
                                                case 'select':
                                                    cell_value = (
                                                        <Select
                                                           optionsMap={stack.mapOpt|| []}
                                                           value={row[stack.param] || ''}
                                                           onChangeSelect={stack.callback.bind(null,row.id)}
                                                           clear={false}
                                                           search={true}
                                                        />
                                                    )
                                                    break;
                                                case 'textarea':
                                                    cell_value = (
                                                        <textarea
                                                            rows = {stack.rows}
                                                            value = {row[stack.param]}
                                                            onChange={(e)=>{
                                                                stack.callback(row.id,e.target.value);
                                                            }}/>
                                                    )
                                                    break;
                                                case 'link':
                                                    cell_value = (
                                                        <a  target='_blank'
                                                            href ={stack.url+row[stack.url_param]} >{row[stack.param]}</a>
                                                    )
                                                    break;
                                                case 'img':
                                                    cell_value = (
                                                        <img src = {row[stack.param]}/>
                                                    )
                                                    break;
                                                case 'html':
                                                    let str_comment = this._is_link(row[stack.param]);
                                                    cell_value = (
                                                        <div dangerouslySetInnerHTML={{__html: str_comment}}></div>
                                                    )
                                                    break;
                                                case 'route':
                                                    const url = stack.url ? stack.url.replace('[param]',row[stack.param]):'';
                                                    cell_value = (
                                                        <Link to={url}>{row[stack.param]}</Link>
                                                    )
                                                    break;
                                                case 'custom':
                                                    const params = {};
                                                    stack.param.forEach((value)=>{
                                                        params[value] = row[value];
                                                    })
                                                    cell_value = stack.creator(params,idx1);
                                                    break;
                                                default: cell_value = row[stack.param];

                                            }
                                            return (
                                                <td key = {idx}
                                                    className = {stack.td_class || ''}
                                                    >
                                                    {cell_value}
                                                </td>
                                            )
                                        })}
                                    </tr>
                                )
                            })
                        }
                        {block_under_table ?
                            <tr>
                                <td colSpan = {stack.length}>
                                    {block_under_table}
                                </td>
                            </tr>:false
                        }
                    </tbody>
                </table>
                {pages.length > 1 ?
                    <ul className='pagination'>
                        {pages}
                    </ul>:''
                }
            </div>
        )
    }
}
