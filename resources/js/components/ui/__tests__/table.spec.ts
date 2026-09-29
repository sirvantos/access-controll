import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../table';

const TableFixture = defineComponent({
    components: { Table, TableBody, TableCell, TableHead, TableHeader, TableRow },
    template: `
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow data-testid="company-row">
                    <TableCell>Acme</TableCell>
                    <TableCell>Active</TableCell>
                </TableRow>
            </TableBody>
        </Table>
    `,
});

describe('Table', () => {
    it('renders semantic table markup and keeps the row testid on tr', () => {
        const wrapper = mount(TableFixture);

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.find('thead').exists()).toBe(true);
        expect(wrapper.find('tbody').exists()).toBe(true);
        expect(wrapper.find('th').exists()).toBe(true);
        expect(wrapper.find('td').exists()).toBe(true);

        const row = wrapper.find('[data-testid="company-row"]');

        expect(row.exists()).toBe(true);
        expect(row.element.tagName).toBe('TR');
        expect(wrapper.findAll('button')).toHaveLength(0);
    });
});
