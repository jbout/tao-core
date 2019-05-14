/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2019 (original work) Open Assessment Technologies SA ;
 */

/**
 * CSS Selectors
 */
const selectors = {
    resourceTree:      '.resource-tree',
    actionsContainer:  '.tree-action-bar',
    contentContainer:  '.content-container',
    itemsRootClass:    '.class[data-uri="http://www.tao.lu/Ontologies/TAOItem.rdf#Item"]',
    deleteClassAction: '.action[data-action="removeNode"][data-context="class"]',
    deleteItemAction:  '.action[data-action="removeNode"][data-context="instance"]',
    toggler:           '.class-toggler',
    treeNode:          '.instance, .class'
};

export default {
    selectors: selectors
};

/**
 * Commands
 */
Cypress.Commands.add('addTreeRoutes', () => {
    cy.route('POST', '**/editItem').as('editItem');
    cy.route('POST', '**/editClassLabel').as('editClass');
    cy.route('POST', '**/deleteItem').as('deleteItem');
    cy.route('POST', '**/deleteClass').as('deleteClass');
});

Cypress.Commands.add('loadItemsPage', () => {
    cy.fixture('urls')
    .as('urls')
    .then(urls => {
        // Provide the full URL parameters including 'uri'
        // to guarantee a predictable tree with the 'Item' root class selected
        cy.visit(`${urls.index}?${urls.taoItemsRoot}&${urls.nosplashParam}`);
        // Important to register this first response, or it will mess up future "wait"s:
        cy.wait('@editClass');
    });
});

Cypress.Commands.add('selectTreeNode', (cssSelector) => {
    cy.log('COMMAND: selectTreeNode', cssSelector);

    cy.get(selectors.resourceTree).within(() => {
        cy.get(cssSelector)
            .then(($el) => {
                const $treeNode = $el.closest(selectors.treeNode);

                // click the node only if it isn't selected:
                if (!$treeNode.hasClass('selected')) {
                    // it can be offscreen due to scrollable panel (so let's force click)
                    cy.wrap($treeNode)
                        .should('not.have.class', 'selected')
                        .click('top', {force: true});

                    // 1 of 2 possible events indicates the clicked node's form loaded:
                    if ($treeNode.hasClass('class')) {
                        cy.wait('@editClass');
                    }
                    else {
                        cy.wait('@editItem');
                    }
                }
            });
    });
});

Cypress.Commands.add('renameSelectedClass', (newName) => {
    cy.log('COMMAND: renameSelectedClass', newName);

    // assumes that editing form has already been rendered
    cy.get(selectors.contentContainer).within(() => {
        cy.contains('label', 'Label')
            .siblings('input')
            .should('be.visible')
            .clear()
            .type(newName);

        cy.contains('Save')
            .click();
    });
    // this event needs to fire twice before proceeding
    cy.wait('@editClass').wait('@editClass').wait(300);
});

Cypress.Commands.add('renameSelectedItem', (newName) => {
    cy.log('COMMAND: renameSelectedItem', newName);

    // assumes that editing form has already been rendered
    cy.get(selectors.contentContainer).within(() => {
        cy.contains('label', 'Label')
            .siblings('input')
            .should('be.visible')
            .clear()
            .type(newName);

        cy.contains('Save')
            .click();
    });
    // this event needs to fire twice before proceeding
    cy.wait(['@editItem', '@editItem']).wait(300);
});

Cypress.Commands.add('addClass', (cssSelector) => {
    cy.log('COMMAND: addClass', cssSelector);

    cy.selectTreeNode(cssSelector);

    cy.contains('New class').click();

    // this event needs to fire twice before proceeding
    cy.wait('@editClass').wait('@editClass');
});

Cypress.Commands.add('addItem', (cssSelector) => {
    cy.log('COMMAND: addItem', cssSelector);

    cy.selectTreeNode(cssSelector);

    cy.contains('New item').click();

    // 2 different events must fire before proceeding
    cy.wait('@editClass').wait('@editItem');
});

Cypress.Commands.add('deleteClass', (cssSelector) => {
    cy.log('COMMAND: deleteClass', cssSelector);

    cy.selectTreeNode(cssSelector);

    cy.get(selectors.deleteClassAction).click({force: true});
    cy.get('.modal-body [data-control="ok"]').click();

    cy.wait('@deleteClass');
});

Cypress.Commands.add('deleteItem', (cssSelector) => {
    cy.log('COMMAND: deleteItem', cssSelector);

    cy.selectTreeNode(cssSelector);

    cy.get(selectors.deleteItemAction).click({force: true});
    cy.get('.modal-body [data-control="ok"]').click();

    cy.wait('@deleteItem');
});
