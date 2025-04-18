import React from 'react'
import { Link} from 'react-router'

/**
    @param {collection of objects}  links_list          - array for link
    @param {bool}                   onlyActiveOnIndex   - if true do not added active class for childs pages
    @param {string}                 url                 - url for links
    @param {string}                 bsClass             - class for active link
    @param {string}                 name                - link name
**/
export default React.createClass({
    render(){
        const links_list = this.props.links_list || []
        return(
            <ul className='nav nav-tabs' role='tablist'>
                {
                    links_list.map((link,idx)=>{
                        return (
                            <li role='presentation' key = {idx}>
                                <Link
                                    to={link.url}
                                    onlyActiveOnIndex = {link.onlyActiveOnIndex}
                                    activeClassName={link.bsClass}>
                                    {link.name}
                                </Link>
                            </li>
                        )
                    })
                }
            </ul>
        )
    }
});
