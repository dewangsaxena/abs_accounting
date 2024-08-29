import { Card, CardBody, VStack } from '@chakra-ui/react'
import React, { memo } from 'react'

// Search Filter
const SearchFilter = memo(() => {
    return <Card bgColor="#EEF5FF">
        <CardBody padding={2}>
              <VStack align="start"></VStack>
        </CardBody>
    </Card>
});

// Customer List
const CustomerList = memo(() => {
  return (
    <div>Customer List</div>
  )
});

export default CustomerList;