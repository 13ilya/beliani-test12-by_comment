import React from 'react'

export default React.createClass(
{
    _is_link(str){

        var pattern = /(https?:\/\/)?([\w.-]?\.?[\w.-]+\.[\w]{2,6}\S*)/gim,
            urls = str.match(pattern),
            parsed_text = str;

        if(!urls) return str;
        urls.forEach((value)=>{
            parsed_text = parsed_text.replace(value,'<a target = "_blank" href="'+value+'">'+ value +'</a>')
        });

        return parsed_text;
    },
    render() {
        const {comments,func,reassignComment,responsible,resp_update_name,bsClass,loggedUserName}=this.props;
        return (
            <div>
                <table className={'table table-bordered table-hover text-center ' + (bsClass || '')}>
                <thead>
                    <tr>
                        <td style={{minWidth:'150px'}}>Date</td>
                        <td style={{minWidth:'150px'}}>Author</td>
                        <td >Comment</td>
                        <td>Delete</td>
                    </tr>
                </thead>
                <tbody>
                {comments.map((value,idx)=>{
                    let link;
                    if (responsible!=value.cusername)
                        link =  (<a href='#'
                                name={value.username}
                                onClick={
                                    (e)=>{
                                        e.preventDefault();
                                        reassignComment(
                                            {
                                                comment_type:'issuelog',
                                                page_id     :value.page_id,
                                                resp_person :value.username
                                            },
                                            '/api/issueLog/changeResponsible/',
                                            resp_update_name
                                        );
                                    }}>
                                    {value.full_username}
                                </a>)
                    else link = value.full_username;
                    let str_comment = value.comment || '';

                    str_comment = str_comment.replace(/\n/gi,'<br/>')

                    return(
                        <tr key={idx} className={value.comment_type}>
                            <td>{value.create_date}</td>
                            <td className='commentUsername'>
                                {link}
                                <div className='commentImageBlock'><img  src={'/images/cache/swedish_src_employee_picid_'+value.employee_id+'_x_200_image.jpg'}/></div>
                            </td>
                            <td className='text-left' style={{wordBreak:'break-all'}}>
                                <div dangerouslySetInnerHTML={{__html: this._is_link(str_comment)}}></div>
                            </td>
                            <td className='control'>
                                {value.prefix != 'alarm' && loggedUserName ==  value.full_username?
                                    <input
                                        type='button'
                                        className='btn btn-default'
                                        value='Delete'
                                        onClick={func.bind(null,
                                            {
                                                comment_id   : value.id,
                                                comment_type : value.comment_type,
                                                page_id      : value.page_id
                                            })}/>:''
                                }
                            </td>
                        </tr>
                    )
                })}

                </tbody>
            </table>
            </div>
        )
    }
})
