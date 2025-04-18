import { connect } from 'react-redux'
import action_creator from '../../../action_creator'
import SelectGroup from '../../../../helpers/selectGroup'
import React from 'react'
import Comments_table from './comments_components/comments_table'
import Comments_mail from './comments_components/comments_mail'
import Comments_alarm from './comments_components/comments_alarm'
export const COMMENTS_VIEW  = React.createClass({
    render() {
            /**
            @param {integer}    ID                      - page id
            @param {object}     COMMENT_BLOCK           - reduce function result object
            @param {string}     RESPONSIBLE             - takes from parrent or from Comment_block if parranet param = 0
            @param {object}     COMMENTS                - support object for new comment
            @param {array}      OPTIONS_MAP             - array with select options
            @param {array}      LIST_OF_BUTTONS         - array with buttons collections for send comment with different params
            **/
        const   commentsOpt = this.props.options || {},
                ID = commentsOpt.page_id || 0,
                url = this.props.url || '',
                name = this.props.name || '',
                fetch = this.props.fetch || '',
                defbtnType = commentsOpt.comment_type || 0;

        const   {
                    commentChange ,
                    addComment,
                    deleteComment,
                    changeAlarmState,
                    changeCommentNotification,
                    reassignComment:parent_reasign
                } = this.props.comments_actions('comments') || {};

        const   COMMENT_BLOCK = this.props.Comment_block || {},
                DATA = COMMENT_BLOCK.data || {},
                OPTIONS = COMMENT_BLOCK.options || {},
                RESPONSIBLE = DATA.responsible_uname ? DATA.responsible_uname : (this.props.responsible_uname || '' ),
                NEW_COMMENT = DATA.new_comment || '';

        const   {
                    comments:COMMENTS =  [],
                    users:OPTIONS_MAP =  [],
                    logs:mailLog =  [],
                    alarm = {},
                    other_params = {}
                } = OPTIONS;


        const   resp_update_name = this.props.resp_update_name,
                LIST_OF_BUTTONS  = this.props.list_of_buttons || [];

        const loggedUserName = _.find(OPTIONS_MAP,item => item.value == $('body').data('user')) || {};

        const   reassignComment = parent_reasign.bind(this,function(){
                if(!NEW_COMMENT) {
                    fetch(url,commentsOpt,name);
                    return false;
                }
                const params = {
                    page_id : ID,
                    comment_type:defbtnType,
                    comment: NEW_COMMENT
                }
                addComment(params);
            });

        let buttons_list = LIST_OF_BUTTONS.map( ( button, key ) => {
                return (
                    <input
                        key = { key }
                        type = 'button'
                        className = {'btn btn-default '+button.type}
                        value = {button.title}
                        onClick = {
                            () => {
                                $('#new_comment').val('');
                                const params = {
                                    page_id : ID,
                                    comment_type:button.type,
                                    comment: NEW_COMMENT
                                    }
                                addComment(params);
                            }
                        }/>
                )
        } )
        return (
            <div className='form-group'>
                <Comments_alarm
                    alarm_send = {(result)=>{
                        let data = {
                            page_id:ID,
                            type:'issuelog',
                            username:loggedUserName.value,
                            status:result.status,
                            comment:alarm.comment || '',
                            date : alarm.date || '',
                            options:{
                                page_id:ID,
                                comment_type:'issuelog'
                            },
                            name
                        }
                        changeAlarmState(data);
                    }}
                    commentChange = {
                        this.props.defaults_actions.generateSimpleAction.bind(null,'ALARM_CHANGE','')
                    }
                    id = {ID}
                    _alarm = {alarm}
                    />
                <Comments_table
                    responsible={RESPONSIBLE}
                    id={ID}
                    bsClass = {this.props.bsClass}
                    reassignComment={reassignComment}
                    comments={COMMENTS}
                    loggedUserName = {loggedUserName.label}
                    resp_update_name={resp_update_name}
                    func={deleteComment}/>
                <br/>
                <div className='form-iinline'>
                    <div className='col-md-6' style={{paddingLeft:'0px'}}>
                        <SelectGroup
                            label='Responsible person:'
                            value={RESPONSIBLE}
                            search={true}
                            list={OPTIONS_MAP}
                            func={commentChange.bind(null,'data','responsible_uname','')}/>
                    </div>
                    <input
                        type='button'
                        className='btn btn-default'
                        value = 'Reassign'
                        onClick={
                            ()=>{
                                $('#new_comment').val('');
                                reassignComment(
                                    {
                                        comment_type:'issuelog',
                                        page_id     :ID,
                                        resp_person :RESPONSIBLE
                                    },
                                    '/api/issueLog/changeResponsible/',
                                    resp_update_name
                                )
                            }
                        }/>
                </div>
                <div className='clearfix'></div>
                <div style={{marginTop:'15px'}}>
                    <p>New comment:</p>
                    <textarea
                        className='form-control'
                        rows='3'
                        style={{height:'100px'}}
                        id='new_comment'
                        value={NEW_COMMENT}
                        onChange={(e)=>{commentChange('data','new_comment','',e.target.value)}}></textarea>
                </div>
                <br/>
                    { buttons_list }
                <div className='clearfix'></div>
                <br/>
                <button
                    onClick={()=>{
                        changeCommentNotification({
                            page_id:ID,
                            options:{
                                page_id:ID,
                                comment_type:'issuelog'
                            },
                            name
                        })
                    }}
                    className = {'btn btn-'+(other_params.comment_notified ? 'success' : 'danger')}
                    >
                    Comment Notification ({other_params.comment_notified ? 'ON' : 'OFF'})
                </button>
                <br/>
                <h4 className='text-center'>Mail log</h4>
                <Comments_mail
                    mailLog = {mailLog}
                />
            </div>
        )
    },
    componentWillMount(){
        this.props.fetch(this.props.url,this.props.options,this.props.name);
        this.props.fetch('/api/filtersOptions/',{type:['users']},'comments_options');
    }
})

const mapStateToProps = function(store) {
    return {
        Comment_block:store.Comment_block
    }
};

const COMMENTS_CONTAINER = connect(mapStateToProps,action_creator)(COMMENTS_VIEW);

export default COMMENTS_CONTAINER
