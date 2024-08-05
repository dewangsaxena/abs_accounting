import {
  Box,
  Card,
  CardBody,
  Divider,
  HStack,
  SimpleGrid,
} from "@chakra-ui/react";
import { cardConfig } from "../../../shared/style";
import { SectionHeader, _Button } from "../../../shared/Components";
import { ReactNode } from "react";

/**
 * This interface defines Action element within a section.
 */
interface ActionDetails {
  /* Action Label */
  label: string;

  /* Icon */
  icon: any;

  /* onClick Handler */
  onClick: () => void;
}

interface ActionProps {
  /* Title of the Section */
  title: string;

  /* Actions */
  actions: ActionDetails[];

  /* Width of each element */
  elementWidth?: number;

  /* Label Font Size */
  labelFontSize?: string;
}

export const ActionGroup = ({
  title,
  actions,
  elementWidth = 200,
  labelFontSize = "1.4em",
}: ActionProps) => {
  return (
    <>
      <Card width={elementWidth * actions.length} height="13vh">
        <CardBody padding={0}>
          <SectionHeader
            letterSpacing={2}
            fontSize="0.6em"
            textAlign={"center"}
          >
            {title}
          </SectionHeader>
          <Divider />
          <HStack spacing={0}>
            {actions.map((action, index) => (
              <Box width={elementWidth} padding={1} key={index}>
                <_Button
                  fontSize={labelFontSize}
                  icon={action.icon}
                  color="black"
                  justifyContent="center"
                  borderRadius={5}
                  height={20}
                  label={action.label}
                  onClick={action.onClick}
                ></_Button>
              </Box>
            ))}
          </HStack>
        </CardBody>
      </Card>
    </>
  );
};

/**
 * This component will render divider based on the count.
 * @param count
 * @returns
 */
export const ActionGroupDivider = ({ count }: { count: number }) => {
  let elements: any[] = [];
  for (let i = 0; i < count; ++i) {
    elements.push(<Divider marginTop={5} marginBottom={5}></Divider>);
  }
  return <>{...elements}</>;
};

/* Canvas */
export const Canvas = ({ children }: { children: ReactNode }) => {
  return (
    <Card
      bgColor="white"
      variant={cardConfig.variant}
      borderRadius={cardConfig.borderRadius}
      minHeight="98vh"
    >
      <CardBody>{children}</CardBody>
    </Card>
  );
};

/* CanvasGrid */
export const CanvasGrid = ({ children }: { children: ReactNode }) => {
  return (
    <Canvas>
      <SimpleGrid
        columns={{ sm: 1, md: 1, lg: 2 }}
        spacing={{ sm: 5, md: 5, lg: 0 }}
      >
        {children}
      </SimpleGrid>
    </Canvas>
  );
};
