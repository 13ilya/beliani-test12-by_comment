import React from 'react';
import PanelCollapsible from './PanelCollapsible'
import Table_component from './table_component'
import {fetchFromApi} from './support_functions'
export default class Page_log extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            page_log:[],
            show_all:false
        }
    }
    update_logs(url){
        this.setState({
            page_log:[],
            show_all:false
        })
        fetchFromApi({url}, '/api/reactPagesLog/setLog', (data)=>{
            console.log(data);
            if(data.page_log){
                this.setState({page_log:data.page_log})
            }
        })
    }
    componentWillMount(){
        const url = location.pathname + location.search + location.hash;
        this.update_logs(url);
    }
    componentWillReceiveProps(props) {
        const url = props.url;
        this.update_logs(url);
    }

    _show_all_toggle(){
        this.setState({show_all:!this.state.show_all});
    }
    render() {
        let   page_log = this.state.page_log,
              width = this.props.width || '90%',
              show_all = this.state.show_all,
              filtered_values,
              log_stack = [
                  {title:'Username', param:'username',no_sortable:true},
                  {title:'Updated', param:'Updated', no_sortable:true},
                  {title:(<a onClick={this._show_all_toggle.bind(this)}>{show_all ? 'Show last' : 'Show all'}</a>), param:'',no_sortable:true}
              ];

        if(!show_all && page_log.length > 5){
            filtered_values = page_log.slice(0,5);
        }else{
            filtered_values = page_log
        }
        return (
            <div style={{width, margin:'auto'}}>
                <PanelCollapsible title={'Page log'}>
                    <div className='col-md-12' style={{maxWidth:'400px'}}>
                        <Table_component
                            stack = {log_stack}
                            filterFieldChange = {()=>{}}
                            values = {filtered_values}
                            />
                    </div>
                </PanelCollapsible>
            </div>
        );
    }
}
